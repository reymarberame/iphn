<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransferStatusRequest;
use App\Models\TransferRecord;
use App\Services\HospitalAccessService;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferApiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly TransferService $transfers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->access->loadContext($user);

        if (! $this->access->hasPermission($user, 'transfers.view')) {
            return $this->error('You do not have permission to view transfers.', 403);
        }

        $hospitalIds = $this->access->patientLinkedHospitalIds($user);

        if ($hospitalIds === []) {
            return $this->error('Patient-linked transfer records are restricted to hospital staff.', 403);
        }

        $query = TransferRecord::query()
            ->whereHas('referral', fn ($builder) => $builder
                ->whereIn('referring_hospital_id', $hospitalIds)
                ->orWhereIn('receiving_hospital_id', $hospitalIds))
            ->with([
                'referral.referringHospital:id,name,short_name',
                'referral.receivingHospital:id,name,short_name',
                'coordinator:id,name',
            ])
            ->when($request->query('status'), fn ($builder, $status) => $builder->where('status', $status))
            ->when($request->query('search'), fn ($builder, $search) => $builder->whereHas('referral', fn ($referral) => $referral->where('referral_number', 'like', '%'.$search.'%')))
            ->latest('updated_at')
            ->paginate(min(100, max(1, (int) $request->query('per_page', 20))))
            ->withQueryString();

        $items = collect($query->items())->map(fn (TransferRecord $transfer) => $this->payload($transfer, $hospitalIds))->values();

        return $this->success($items, 'Transfers retrieved.', 200, [
            'pagination' => [
                'current_page' => $query->currentPage(),
                'per_page' => $query->perPage(),
                'total' => $query->total(),
                'last_page' => $query->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, TransferRecord $transfer): JsonResponse
    {
        $this->authorize('view', $transfer);
        $transfer->load(['referral.referringHospital', 'referral.receivingHospital', 'coordinator:id,name']);

        return $this->success($this->payload($transfer, $this->access->managementHospitalIds($request->user())));
    }

    public function departure(TransferStatusRequest $request, TransferRecord $transfer): JsonResponse
    {
        $this->authorize('view', $transfer);
        $updated = $this->transfers->departure($transfer, $request->user(), $request->validated());

        return $this->success($this->payload($updated->fresh(['referral', 'coordinator']), []), 'Departure recorded.');
    }

    public function arrival(TransferStatusRequest $request, TransferRecord $transfer): JsonResponse
    {
        $this->authorize('view', $transfer);
        $updated = $this->transfers->arrival($transfer, $request->user(), $request->validated());

        return $this->success($this->payload($updated->fresh(['referral', 'coordinator']), []), 'Arrival confirmed.');
    }

    public function complete(Request $request, TransferRecord $transfer): JsonResponse
    {
        $this->authorize('view', $transfer);
        $updated = $this->transfers->complete($transfer, $request->user());

        return $this->success($this->payload($updated->fresh(['referral', 'coordinator']), []), 'Transfer completed.');
    }

    private function payload(TransferRecord $transfer, array $hospitalIds): array
    {
        $referral = $transfer->referral;

        return [
            'id' => $transfer->id,
            'referral_id' => $referral?->id,
            'referral_number' => $referral?->referral_number,
            'referral_status' => $referral?->status,
            'referral_status_label' => $referral?->statusLabel(),
            'status' => $transfer->status,
            'status_label' => $transfer->statusLabel(),
            'transport_type' => $transfer->transport_type,
            'transport_label' => TransferRecord::TRANSPORT_LABELS[$transfer->transport_type] ?? $transfer->transport_type,
            'vehicle_reference' => $transfer->vehicle_reference,
            'escort_contact' => $transfer->escort_contact,
            'referring_hospital' => $referral?->referringHospital?->short_name ?? $referral?->referringHospital?->name,
            'receiving_hospital' => $referral?->receivingHospital?->short_name ?? $referral?->receivingHospital?->name,
            'is_incoming' => $referral && in_array($referral->receiving_hospital_id, $hospitalIds, true),
            'departure_at' => $transfer->departure_at?->toIso8601String(),
            'estimated_arrival_at' => $transfer->estimated_arrival_at?->toIso8601String(),
            'arrival_at' => $transfer->arrival_at?->toIso8601String(),
            'coordinator' => $transfer->coordinator?->name,
            'notes' => $transfer->notes,
            'created_at' => $transfer->created_at?->toIso8601String(),
            'updated_at' => $transfer->updated_at?->toIso8601String(),
        ];
    }
}
