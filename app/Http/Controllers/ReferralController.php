<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeclineReferralRequest;
use App\Http\Requests\RequestInformationRequest;
use App\Http\Requests\RespondReferralRequest;
use App\Http\Requests\StoreReferralRequest;
use App\Http\Requests\StoreTransferRequest;
use App\Http\Requests\TransferStatusRequest;
use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Referral;
use App\Models\ReferralDocument;
use App\Models\TransferRecord;
use App\Services\BedAvailabilityService;
use App\Services\BedReservationService;
use App\Services\HospitalAccessService;
use App\Services\ReferralService;
use App\Services\TransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly BedAvailabilityService $availability,
        private readonly ReferralService $referrals,
        private readonly BedReservationService $reservations,
        private readonly TransferService $transfers,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->access->loadContext($user);

        if (! $this->access->hasPermission($user, 'referrals.view')) {
            abort(403, 'You do not have permission to view referrals.');
        }

        $hospitalIds = $this->access->patientLinkedHospitalIds($user);

        if ($hospitalIds === []) {
            abort(403, 'Patient-linked referral records are restricted to the referring and receiving hospitals.');
        }

        $filters = $request->only(['direction', 'status', 'urgency', 'search']);
        $statuses = array_keys(Referral::STATUS_LABELS);
        $pendingStatuses = [
            Referral::STATUS_SUBMITTED, Referral::STATUS_UNDER_REVIEW, Referral::STATUS_MORE_INFO,
            Referral::STATUS_ACCEPTED, Referral::STATUS_TRANSFER_SCHEDULED, Referral::STATUS_IN_TRANSIT,
        ];

        $query = Referral::query()
            ->where(fn ($builder) => $builder
                ->whereIn('referring_hospital_id', $hospitalIds)
                ->orWhereIn('receiving_hospital_id', $hospitalIds))
            ->with([
                'referringHospital:id,name,short_name',
                'receivingHospital:id,name,short_name',
                'requestedService:id,name',
                'coordinator:id,name',
            ])
            ->when(($filters['direction'] ?? null) === 'incoming', fn ($builder) => $builder->whereIn('receiving_hospital_id', $hospitalIds))
            ->when(($filters['direction'] ?? null) === 'outgoing', fn ($builder) => $builder->whereIn('referring_hospital_id', $hospitalIds))
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when($filters['urgency'] ?? null, fn ($builder, $urgency) => $builder->where('urgency', $urgency))
            ->when($filters['search'] ?? null, function ($builder, $search) {
                $builder->where(fn ($inner) => $inner
                    ->where('referral_number', 'like', '%'.$search.'%')
                    ->orWhere('patient_reference', 'like', '%'.$search.'%')
                    ->orWhere('patient_name', 'like', '%'.$search.'%'));
            })
            ->latest('created_at')
            ->paginate(12)
            ->withQueryString()
            ->through(function (Referral $referral) use ($hospitalIds) {
                $isIncoming = in_array($referral->receiving_hospital_id, $hospitalIds, true);
                $isOutgoing = in_array($referral->referring_hospital_id, $hospitalIds, true);

                return [
                    'id' => $referral->id,
                    'referral_number' => $referral->referral_number,
                    'patient_reference' => $referral->patient_reference,
                    'patient_name' => $referral->patient_name,
                    'patient_age' => $referral->patient_age,
                    'patient_sex' => $referral->patient_sex,
                    'urgency' => $referral->urgency,
                    'urgency_label' => $referral->urgencyLabel(),
                    'status' => $referral->status,
                    'status_label' => $referral->statusLabel(),
                    'direction' => $isIncoming && $isOutgoing ? 'both' : ($isIncoming ? 'incoming' : 'outgoing'),
                    'is_incoming' => $isIncoming,
                    'requested_service' => $referral->requestedService?->name,
                    'referring_hospital' => $referral->referringHospital?->short_name ?? $referral->referringHospital?->name,
                    'receiving_hospital' => $referral->receivingHospital?->short_name ?? $referral->receivingHospital?->name,
                    'coordinator' => $referral->coordinator?->name,
                    'created_at' => $referral->created_at?->toIso8601String(),
                    'submitted_at' => $referral->submitted_at?->toIso8601String(),
                ];
            });

        $counts = [
            'pending_incoming' => Referral::query()
                ->whereIn('receiving_hospital_id', $hospitalIds)
                ->whereIn('status', $pendingStatuses)->count(),
            'pending_outgoing' => Referral::query()
                ->whereIn('referring_hospital_id', $hospitalIds)
                ->whereIn('status', $pendingStatuses)->count(),
            'accepted' => Referral::query()
                ->where(fn ($builder) => $builder->whereIn('receiving_hospital_id', $hospitalIds)->orWhereIn('referring_hospital_id', $hospitalIds))
                ->whereIn('status', [Referral::STATUS_ACCEPTED, Referral::STATUS_TRANSFER_SCHEDULED, Referral::STATUS_IN_TRANSIT])
                ->count(),
            'completed_last_30_days' => Referral::query()
                ->where(fn ($builder) => $builder->whereIn('receiving_hospital_id', $hospitalIds)->orWhereIn('referring_hospital_id', $hospitalIds))
                ->where('status', Referral::STATUS_COMPLETED)
                ->where('closed_at', '>=', now()->subDays(30))
                ->count(),
        ];

        $canCreate = $this->access->activeMemberships($user)
            ->contains(fn ($membership) => $membership->role?->permissions->contains('name', 'referrals.create'));

        return Inertia::render('Referrals/Index', [
            'referrals' => $query,
            'filters' => $filters,
            'statusOptions' => collect(Referral::STATUS_LABELS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'urgencyOptions' => collect(Referral::URGENCY_LABELS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'counts' => $counts,
            'canCreate' => $canCreate,
            'statuses' => $statuses,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $this->access->loadContext($user);

        $referring = $this->access->activeMemberships($user)
            ->filter(fn ($membership) => $membership->role?->permissions->contains('name', 'referrals.create'))
            ->map(fn ($membership) => $membership->hospital)
            ->filter()
            ->unique('id')
            ->values();

        if ($referring->isEmpty()) {
            abort(403, 'You do not have a referral-capable hospital assignment.');
        }

        $receiving = $this->availability->filteredHospitalsQuery($user, [])
            ->whereNotIn('id', $referring->pluck('id'))
            ->limit(100)
            ->get()
            ->map(function (Hospital $hospital) {
                $summary = $this->availability->hospitalSummary($hospital);

                return [
                    'id' => $hospital->id,
                    'name' => $hospital->name,
                    'short_name' => $hospital->short_name,
                    'hospital_code' => $hospital->hospital_code,
                    'ownership_type' => $hospital->ownership_type,
                    'facility_type' => $hospital->facility_type,
                    'status' => $hospital->status,
                    'location' => $hospital->location_label,
                    'phone' => $hospital->phone,
                    'service_ids' => $hospital->services->pluck('id')->values(),
                    'services' => $hospital->services->pluck('name')->values(),
                    'counts' => $summary['counts'],
                    'last_updated_at' => $summary['last_updated_at'],
                    'freshness' => $summary['freshness'],
                    'freshness_label' => $summary['freshness_label'],
                    'categories' => $this->availability->categoryBreakdown($hospital->id),
                ];
            })->values();

        return Inertia::render('Referrals/Create', [
            'referringHospitals' => $referring->map(fn (Hospital $hospital) => [
                'id' => $hospital->id,
                'name' => $hospital->name,
                'short_name' => $hospital->short_name,
                'location' => $hospital->location_label,
            ])->values(),
            'receivingHospitals' => $receiving,
            'preselectedReceivingId' => $request->integer('receiving_hospital') ?: null,
            'urgencyOptions' => collect(Referral::URGENCY_LABELS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    public function store(StoreReferralRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['submit'] = $request->boolean('submit');

        $referral = $this->referrals->create($request->user(), $data);

        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $this->referrals->uploadDocument($referral, $request->user(), $file);
            }
        }

        return redirect()->route('referrals.show', $referral)->with(
            'success',
            $data['submit'] ? 'Referral submitted to the receiving hospital.' : 'Referral saved as a draft.'
        );
    }

    public function show(Request $request, Referral $referral): Response
    {
        $this->authorize('view', $referral);

        $referral->load([
            'referringHospital.municipality.province.region',
            'receivingHospital.municipality.province.region',
            'requestedService',
            'creator:id,name,email,phone',
            'coordinator:id,name,email,phone',
            'statusHistory.changer:id,name',
            'documents.uploader:id,name',
            'activeReservation.bed.category',
            'activeReservation.bed.room.ward',
            'transfer',
        ]);

        $abilities = $this->access->referralAbilities($request->user(), $referral);
        $receivingAvailability = $this->availability->hospitalAvailability($referral->receiving_hospital_id);

        $availableBeds = collect();
        if ($abilities['can_reserve_bed'] ?? false) {
            $availableBeds = Bed::query()
                ->where('hospital_id', $referral->receiving_hospital_id)
                ->where('active', true)
                ->where('status', Bed::STATUS_AVAILABLE)
                ->with(['category:id,name,slug,is_er', 'room.ward:id,name,ward_type'])
                ->orderBy('bed_code')
                ->limit(250)
                ->get()
                ->map(fn (Bed $bed) => [
                    'id' => $bed->id,
                    'bed_code' => $bed->bed_code,
                    'category' => $bed->category?->name,
                    'category_slug' => $bed->category?->slug,
                    'is_er' => $bed->category?->is_er,
                    'ward' => $bed->room?->ward?->name,
                    'room' => $bed->room?->room_code,
                ]);
        }

        $coordinators = $this->access->usersWithHospitalPermission($referral->receiving_hospital_id, 'referrals.respond')
            ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
            ->values();

        return Inertia::render('Referrals/Show', [
            'referral' => [
                'id' => $referral->id,
                'public_id' => $referral->public_id,
                'referral_number' => $referral->referral_number,
                'status' => $referral->status,
                'status_label' => $referral->statusLabel(),
                'urgency' => $referral->urgency,
                'urgency_label' => $referral->urgencyLabel(),
                'patient_reference' => $referral->patient_reference,
                'patient_name' => $referral->patient_name,
                'patient_age' => $referral->patient_age,
                'patient_sex' => $referral->patient_sex,
                'referral_reason' => $referral->referral_reason,
                'requested_service' => $referral->requestedService?->name,
                'contact_name' => $referral->contact_name,
                'contact_phone' => $referral->contact_phone,
                'response_note' => $referral->response_note,
                'decline_reason' => $referral->decline_reason,
                'created_at' => $referral->created_at?->toIso8601String(),
                'submitted_at' => $referral->submitted_at?->toIso8601String(),
                'accepted_at' => $referral->accepted_at?->toIso8601String(),
                'closed_at' => $referral->closed_at?->toIso8601String(),
                'expires_at' => $referral->expires_at?->toIso8601String(),
                'referring_hospital' => $this->hospitalPayload($referral->referringHospital),
                'receiving_hospital' => $this->hospitalPayload($referral->receivingHospital),
                'creator' => $referral->creator?->only(['id', 'name', 'email', 'phone']),
                'coordinator' => $referral->coordinator?->only(['id', 'name', 'email', 'phone']),
                'status_history' => $referral->statusHistory->map(fn ($history) => [
                    'id' => $history->id,
                    'old_status' => $history->old_status,
                    'new_status' => $history->new_status,
                    'reason' => $history->reason,
                    'changed_by' => $history->changer?->name ?? 'System',
                    'changed_at' => $history->changed_at?->toIso8601String(),
                ])->values(),
                'documents' => $referral->documents->map(fn (ReferralDocument $document) => [
                    'id' => $document->id,
                    'original_name' => $document->original_name,
                    'mime_type' => $document->mime_type,
                    'size_bytes' => $document->size_bytes,
                    'uploaded_by' => $document->uploader?->name,
                    'created_at' => $document->created_at?->toIso8601String(),
                ])->values(),
                'reservation' => $referral->activeReservation ? [
                    'id' => $referral->activeReservation->id,
                    'bed_id' => $referral->activeReservation->bed_id,
                    'bed_code' => $referral->activeReservation->bed?->bed_code,
                    'category' => $referral->activeReservation->bed?->category?->name,
                    'ward' => $referral->activeReservation->bed?->room?->ward?->name,
                    'reserved_at' => $referral->activeReservation->reserved_at?->toIso8601String(),
                    'expires_at' => $referral->activeReservation->expires_at?->toIso8601String(),
                ] : null,
                'transfer' => $referral->transfer ? [
                    'id' => $referral->transfer->id,
                    'status' => $referral->transfer->status,
                    'status_label' => $referral->transfer->statusLabel(),
                    'transport_type' => $referral->transfer->transport_type,
                    'transport_label' => TransferRecord::TRANSPORT_LABELS[$referral->transfer->transport_type] ?? $referral->transfer->transport_type,
                    'vehicle_reference' => $referral->transfer->vehicle_reference,
                    'escort_contact' => $referral->transfer->escort_contact,
                    'departure_at' => $referral->transfer->departure_at?->toIso8601String(),
                    'estimated_arrival_at' => $referral->transfer->estimated_arrival_at?->toIso8601String(),
                    'arrival_at' => $referral->transfer->arrival_at?->toIso8601String(),
                    'notes' => $referral->transfer->notes,
                    'coordinator' => $referral->transfer->coordinator?->name,
                ] : null,
            ],
            'abilities' => $abilities,
            'receivingAvailability' => $receivingAvailability,
            'availableBeds' => $availableBeds,
            'coordinators' => $coordinators,
            'transportOptions' => collect(TransferRecord::TRANSPORT_LABELS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    public function submit(Request $request, Referral $referral): RedirectResponse
    {
        $this->authorize('view', $referral);
        $this->referrals->submit($referral, $request->user());

        return back()->with('success', 'Referral submitted. The receiving hospital has been notified.');
    }

    public function startReview(Request $request, Referral $referral): RedirectResponse
    {
        $this->authorize('view', $referral);
        $this->referrals->startReview($referral, $request->user());

        return back()->with('success', 'Referral marked under review.');
    }

    public function accept(RespondReferralRequest $request, Referral $referral): RedirectResponse
    {
        $this->authorize('view', $referral);
        $this->referrals->accept($referral, $request->user(), $request->validated());

        return back()->with('success', 'Referral accepted. You can now reserve a bed and coordinate the transfer.');
    }

    public function decline(DeclineReferralRequest $request, Referral $referral): RedirectResponse
    {
        $this->authorize('view', $referral);
        $this->referrals->decline($referral, $request->user(), $request->validated('decline_reason'));

        return back()->with('success', 'Referral declined. The referring hospital has been notified.');
    }

    public function requestInformation(RequestInformationRequest $request, Referral $referral): RedirectResponse
    {
        $this->authorize('view', $referral);
        $this->referrals->requestInformation($referral, $request->user(), $request->validated('note'));

        return back()->with('success', 'The referring hospital has been asked for more information.');
    }

    public function cancel(Request $request, Referral $referral): RedirectResponse
    {
        $this->authorize('view', $referral);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $this->referrals->cancel($referral, $request->user(), $validated['reason']);

        return back()->with('success', 'Referral cancelled. Any active bed hold was released.');
    }

    public function assignCoordinator(Request $request, Referral $referral): RedirectResponse
    {
        $this->authorize('view', $referral);
        $validated = $request->validate(['coordinator_id' => ['required', 'integer', 'exists:users,id']]);
        $this->referrals->assignCoordinator($referral, $request->user(), (int) $validated['coordinator_id']);

        return back()->with('success', 'Referral coordinator assigned.');
    }

    public function reserveBed(Request $request, Referral $referral): RedirectResponse
    {
        $this->authorize('view', $referral);
        $validated = $request->validate(['bed_id' => ['required', 'integer', 'exists:beds,id']]);
        $bed = Bed::query()->findOrFail($validated['bed_id']);
        $this->reservations->reserve($referral, $bed, $request->user());

        return back()->with('success', $bed->bed_code.' reserved. The bed is now held for this referral.');
    }

    public function scheduleTransfer(StoreTransferRequest $request, Referral $referral): RedirectResponse
    {
        $this->authorize('view', $referral);
        $this->transfers->create($referral, $request->user(), $request->validated());

        return back()->with('success', 'Transfer scheduled. The receiving hospital has been notified.');
    }

    public function departure(TransferStatusRequest $request, Referral $referral): RedirectResponse
    {
        $this->authorize('view', $referral);

        if (! $referral->transfer) {
            abort(404, 'No transfer record exists for this referral.');
        }

        $this->transfers->departure($referral->transfer, $request->user(), $request->validated());

        return back()->with('success', 'Departure recorded. The patient is now marked in transit.');
    }

    public function arrival(TransferStatusRequest $request, Referral $referral): RedirectResponse
    {
        $this->authorize('view', $referral);

        if (! $referral->transfer) {
            abort(404, 'No transfer record exists for this referral.');
        }

        $this->transfers->arrival($referral->transfer, $request->user(), $request->validated());

        return back()->with('success', 'Arrival confirmed. The reserved bed is now occupied.');
    }

    public function completeTransfer(Request $request, Referral $referral): RedirectResponse
    {
        $this->authorize('view', $referral);

        if (! $referral->transfer) {
            abort(404, 'No transfer record exists for this referral.');
        }

        $this->transfers->complete($referral->transfer, $request->user());

        return back()->with('success', 'Transfer completed. No clinical record is stored by IPHN.');
    }

    public function uploadDocument(Request $request, Referral $referral): RedirectResponse
    {
        $this->authorize('view', $referral);

        $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);

        $this->referrals->uploadDocument($referral, $request->user(), $request->file('document'));

        return back()->with('success', 'Supporting document attached securely.');
    }

    public function downloadDocument(Request $request, Referral $referral, ReferralDocument $document): mixed
    {
        abort_unless($document->referral_id === $referral->id, 404);
        $this->authorize('view', $referral);

        return Storage::disk('local')->download($document->storage_path, $document->original_name);
    }

    private function hospitalPayload(?Hospital $hospital): ?array
    {
        if (! $hospital) {
            return null;
        }

        return [
            'id' => $hospital->id,
            'name' => $hospital->name,
            'short_name' => $hospital->short_name,
            'hospital_code' => $hospital->hospital_code,
            'ownership_type' => $hospital->ownership_type,
            'ownership_label' => $hospital->ownershipLabel(),
            'address' => $hospital->address,
            'phone' => $hospital->phone,
            'email' => $hospital->email,
            'location' => $hospital->location_label,
            'province' => $hospital->province?->name,
            'region' => $hospital->region?->short_name,
        ];
    }
}
