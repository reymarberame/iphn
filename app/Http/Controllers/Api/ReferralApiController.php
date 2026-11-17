<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralApiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly BedAvailabilityService $availability,
        private readonly ReferralService $referrals,
        private readonly BedReservationService $reservations,
        private readonly TransferService $transfers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->access->loadContext($user);

        if (! $this->access->hasPermission($user, 'referrals.view')) {
            return $this->error('You do not have permission to view referrals.', 403);
        }

        $hospitalIds = $this->access->patientLinkedHospitalIds($user);

        if ($hospitalIds === []) {
            return $this->error('Patient-linked referral records are restricted to hospital staff.', 403);
        }

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
            ->when($request->query('direction') === 'incoming', fn ($builder) => $builder->whereIn('receiving_hospital_id', $hospitalIds))
            ->when($request->query('direction') === 'outgoing', fn ($builder) => $builder->whereIn('referring_hospital_id', $hospitalIds))
            ->when($request->query('status'), fn ($builder, $status) => $builder->where('status', $status))
            ->when($request->query('urgency'), fn ($builder, $urgency) => $builder->where('urgency', $urgency))
            ->when($request->query('search'), function ($builder, $search) {
                $builder->where(fn ($inner) => $inner
                    ->where('referral_number', 'like', '%'.$search.'%')
                    ->orWhere('patient_reference', 'like', '%'.$search.'%')
                    ->orWhere('patient_name', 'like', '%'.$search.'%'));
            })
            ->latest('created_at')
            ->paginate(min(100, max(1, (int) $request->query('per_page', 20))))
            ->withQueryString();

        $items = collect($query->items())->map(fn (Referral $referral) => $this->summaryPayload($referral, $hospitalIds))->values();

        return $this->success($items, 'Referrals retrieved.', 200, [
            'pagination' => [
                'current_page' => $query->currentPage(),
                'per_page' => $query->perPage(),
                'total' => $query->total(),
                'last_page' => $query->lastPage(),
            ],
        ]);
    }

    public function store(StoreReferralRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['submit'] = $request->boolean('submit');

        $referral = $this->referrals->create($request->user(), $data);

        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $this->referrals->uploadDocument($referral, $request->user(), $file);
            }
        }

        return $this->success($this->payload($referral->fresh()), $data['submit'] ? 'Referral submitted.' : 'Referral saved as a draft.', 201);
    }

    public function show(Request $request, Referral $referral): JsonResponse
    {
        $this->authorize('view', $referral);

        return $this->success($this->payload($referral, $request));
    }

    public function submit(Request $request, Referral $referral): JsonResponse
    {
        $this->authorize('view', $referral);
        $referral = $this->referrals->submit($referral, $request->user());

        return $this->success($this->payload($referral), 'Referral submitted.');
    }

    public function startReview(Request $request, Referral $referral): JsonResponse
    {
        $this->authorize('view', $referral);
        $referral = $this->referrals->startReview($referral, $request->user());

        return $this->success($this->payload($referral), 'Referral marked under review.');
    }

    public function accept(RespondReferralRequest $request, Referral $referral): JsonResponse
    {
        $this->authorize('view', $referral);
        $referral = $this->referrals->accept($referral, $request->user(), $request->validated());

        return $this->success($this->payload($referral), 'Referral accepted.');
    }

    public function decline(DeclineReferralRequest $request, Referral $referral): JsonResponse
    {
        $this->authorize('view', $referral);
        $referral = $this->referrals->decline($referral, $request->user(), $request->validated('decline_reason'));

        return $this->success($this->payload($referral), 'Referral declined.');
    }

    public function requestInformation(RequestInformationRequest $request, Referral $referral): JsonResponse
    {
        $this->authorize('view', $referral);
        $referral = $this->referrals->requestInformation($referral, $request->user(), $request->validated('note'));

        return $this->success($this->payload($referral), 'Additional information requested.');
    }

    public function cancel(Request $request, Referral $referral): JsonResponse
    {
        $this->authorize('view', $referral);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $referral = $this->referrals->cancel($referral, $request->user(), $validated['reason']);

        return $this->success($this->payload($referral), 'Referral cancelled and any bed hold released.');
    }

    public function assignCoordinator(Request $request, Referral $referral): JsonResponse
    {
        $this->authorize('view', $referral);
        $validated = $request->validate(['coordinator_id' => ['required', 'integer', 'exists:users,id']]);
        $referral = $this->referrals->assignCoordinator($referral, $request->user(), (int) $validated['coordinator_id']);

        return $this->success($this->payload($referral), 'Coordinator assigned.');
    }

    public function reserveBed(Request $request, Referral $referral): JsonResponse
    {
        $this->authorize('view', $referral);
        $validated = $request->validate(['bed_id' => ['required', 'integer', 'exists:beds,id']]);
        $bed = Bed::query()->findOrFail($validated['bed_id']);
        $reservation = $this->reservations->reserve($referral, $bed, $request->user());

        return $this->success([
            'reservation_id' => $reservation->id,
            'bed_id' => $reservation->bed_id,
            'bed_code' => $reservation->bed?->bed_code,
            'expires_at' => $reservation->expires_at?->toIso8601String(),
        ], $bed->bed_code.' reserved for this referral.', 201);
    }

    public function scheduleTransfer(StoreTransferRequest $request, Referral $referral): JsonResponse
    {
        $this->authorize('view', $referral);
        $this->transfers->create($referral, $request->user(), $request->validated());

        return $this->success($this->payload($referral->fresh()), 'Transfer scheduled.');
    }

    public function departure(TransferStatusRequest $request, Referral $referral): JsonResponse
    {
        $this->authorize('view', $referral);
        $transfer = $referral->transfer;

        if (! $transfer) {
            return $this->error('No transfer record exists for this referral.', 404);
        }

        $this->transfers->departure($transfer, $request->user(), $request->validated());

        return $this->success($this->payload($referral->fresh()), 'Departure recorded.');
    }

    public function arrival(TransferStatusRequest $request, Referral $referral): JsonResponse
    {
        $this->authorize('view', $referral);
        $transfer = $referral->transfer;

        if (! $transfer) {
            return $this->error('No transfer record exists for this referral.', 404);
        }

        $this->transfers->arrival($transfer, $request->user(), $request->validated());

        return $this->success($this->payload($referral->fresh()), 'Arrival confirmed.');
    }

    public function complete(Request $request, Referral $referral): JsonResponse
    {
        $this->authorize('view', $referral);
        $transfer = $referral->transfer;

        if (! $transfer) {
            return $this->error('No transfer record exists for this referral.', 404);
        }

        $this->transfers->complete($transfer, $request->user());

        return $this->success($this->payload($referral->fresh()), 'Transfer completed.');
    }

    public function uploadDocument(Request $request, Referral $referral): JsonResponse
    {
        $this->authorize('view', $referral);

        $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);

        $document = $this->referrals->uploadDocument($referral, $request->user(), $request->file('document'));

        return $this->success([
            'id' => $document->id,
            'original_name' => $document->original_name,
            'size_bytes' => $document->size_bytes,
            'created_at' => $document->created_at?->toIso8601String(),
        ], 'Supporting document attached.', 201);
    }

    private function summaryPayload(Referral $referral, array $hospitalIds): array
    {
        $isIncoming = in_array($referral->receiving_hospital_id, $hospitalIds, true);
        $isOutgoing = in_array($referral->referring_hospital_id, $hospitalIds, true);

        return [
            'id' => $referral->id,
            'public_id' => $referral->public_id,
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
            'requested_service' => $referral->requestedService?->name,
            'referring_hospital' => $referral->referringHospital?->short_name ?? $referral->referringHospital?->name,
            'receiving_hospital' => $referral->receivingHospital?->short_name ?? $referral->receivingHospital?->name,
            'coordinator' => $referral->coordinator?->name,
            'submitted_at' => $referral->submitted_at?->toIso8601String(),
            'created_at' => $referral->created_at?->toIso8601String(),
        ];
    }

    private function payload(Referral $referral, ?Request $request = null): array
    {
        $referral->loadMissing([
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

        $abilities = $request ? $this->access->referralAbilities($request->user(), $referral) : [];
        $availableBeds = collect();

        if (($abilities['can_reserve_bed'] ?? false) && $request) {
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
                    'is_er' => $bed->category?->is_er,
                    'ward' => $bed->room?->ward?->name,
                    'room' => $bed->room?->room_code,
                ]);
        }

        return [
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
            ] : null,
            'abilities' => $abilities,
            'available_beds' => $availableBeds,
            'receiving_availability' => $request ? $this->availability->hospitalAvailability($referral->receiving_hospital_id) : null,
        ];
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
