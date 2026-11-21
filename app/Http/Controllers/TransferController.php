<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\TransferRecord;
use App\Services\HospitalAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function __construct(private readonly HospitalAccessService $access) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->access->loadContext($user);

        if (! $this->access->hasPermission($user, 'transfers.view')) {
            abort(403, 'You do not have permission to view transfers.');
        }

        $hospitalIds = $this->access->patientLinkedHospitalIds($user);

        if ($hospitalIds === []) {
            abort(403, 'Patient-linked transfer records are restricted to the referring and receiving hospitals.');
        }

        $filters = $request->only(['status', 'search']);

        $query = TransferRecord::query()
            ->whereHas('referral', fn ($builder) => $builder
                ->whereIn('referring_hospital_id', $hospitalIds)
                ->orWhereIn('receiving_hospital_id', $hospitalIds))
            ->with([
                'referral.referringHospital:id,name,short_name',
                'referral.receivingHospital:id,name,short_name',
                'coordinator:id,name',
            ])
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when($filters['search'] ?? null, fn ($builder, $search) => $builder->whereHas('referral', fn ($referral) => $referral->whereLike('referral_number', '%'.$search.'%')))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(function (TransferRecord $transfer) use ($hospitalIds) {
                $referral = $transfer->referral;

                return [
                    'id' => $transfer->id,
                    'referral_id' => $referral?->id,
                    'referral_number' => $referral?->referral_number,
                    'referral_status' => $referral?->status,
                    'referral_status_label' => $referral?->statusLabel(),
                    'status' => $transfer->status,
                    'status_label' => $transfer->statusLabel(),
                    'transport_label' => TransferRecord::TRANSPORT_LABELS[$transfer->transport_type] ?? $transfer->transport_type,
                    'vehicle_reference' => $transfer->vehicle_reference,
                    'referring_hospital' => $referral?->referringHospital?->short_name ?? $referral?->referringHospital?->name,
                    'receiving_hospital' => $referral?->receivingHospital?->short_name ?? $referral?->receivingHospital?->name,
                    'is_incoming' => in_array($referral?->receiving_hospital_id, $hospitalIds, true),
                    'departure_at' => $transfer->departure_at?->toIso8601String(),
                    'estimated_arrival_at' => $transfer->estimated_arrival_at?->toIso8601String(),
                    'arrival_at' => $transfer->arrival_at?->toIso8601String(),
                    'coordinator' => $transfer->coordinator?->name,
                    'updated_at' => $transfer->updated_at?->toIso8601String(),
                ];
            });

        $counts = [
            'scheduled' => TransferRecord::query()
                ->whereHas('referral', fn ($builder) => $builder->whereIn('referring_hospital_id', $hospitalIds)->orWhereIn('receiving_hospital_id', $hospitalIds))
                ->whereIn('status', [TransferRecord::STATUS_SCHEDULED, TransferRecord::STATUS_READY])->count(),
            'in_transit' => TransferRecord::query()
                ->whereHas('referral', fn ($builder) => $builder->whereIn('referring_hospital_id', $hospitalIds)->orWhereIn('receiving_hospital_id', $hospitalIds))
                ->where('status', TransferRecord::STATUS_IN_TRANSIT)->count(),
            'arrived' => TransferRecord::query()
                ->whereHas('referral', fn ($builder) => $builder->whereIn('referring_hospital_id', $hospitalIds)->orWhereIn('receiving_hospital_id', $hospitalIds))
                ->where('status', TransferRecord::STATUS_ARRIVED)->count(),
            'completed_last_30_days' => TransferRecord::query()
                ->whereHas('referral', fn ($builder) => $builder->whereIn('referring_hospital_id', $hospitalIds)->orWhereIn('receiving_hospital_id', $hospitalIds))
                ->where('status', TransferRecord::STATUS_COMPLETED)
                ->where('updated_at', '>=', now()->subDays(30))->count(),
        ];

        return Inertia::render('Transfers/Index', [
            'transfers' => $query,
            'filters' => $filters,
            'counts' => $counts,
            'statusOptions' => collect(TransferRecord::STATUS_LABELS)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'statusTransitions' => [
                Referral::STATUS_ACCEPTED => Referral::STATUS_LABELS[Referral::STATUS_ACCEPTED],
            ],
        ]);
    }
}
