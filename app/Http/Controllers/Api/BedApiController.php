<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\StaleBedUpdateException;
use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\BedStatusHistory;
use App\Models\Hospital;
use App\Models\Room;
use App\Services\AuditLogger;
use App\Services\BedAvailabilityService;
use App\Services\BedManagementService;
use App\Services\HospitalAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BedApiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly BedAvailabilityService $availability,
        private readonly BedManagementService $beds,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->access->loadContext($user);

        $hospitalIds = $this->access->managementHospitalIds($user);

        if ($hospitalIds === []) {
            return $this->error('Your account has no hospital assignment.', 403);
        }

        $hospitalId = (int) $request->query('hospital_id', $hospitalIds[0]);

        if (! in_array($hospitalId, $hospitalIds, true)) {
            return $this->error('You do not have access to that hospital.', 403);
        }

        $query = Bed::query()
            ->where('hospital_id', $hospitalId)
            ->where('active', true)
            ->with(['category:id,name,slug,is_er', 'room.ward:id,name,ward_type', 'updater:id,name', 'activeReservation.referral:id,referral_number,status'])
            ->when($request->query('ward_id'), fn ($builder, $wardId) => $builder->whereHas('room', fn ($room) => $room->where('ward_id', $wardId)))
            ->when($request->query('status'), fn ($builder, $status) => $builder->where('status', $status))
            ->when($request->query('category'), fn ($builder, $category) => $builder->whereHas('category', fn ($cat) => $cat->where('slug', $category)))
            ->when($request->query('search'), fn ($builder, $search) => $builder->where('bed_code', 'like', '%'.$search.'%'))
            ->orderBy('bed_code');

        $beds = $query->get()->map(fn (Bed $bed) => $this->payload($bed))->values();

        return $this->success($beds, 'Bed inventory retrieved.', 200, [
            'hospital_id' => $hospitalId,
            'count' => $beds->count(),
            'summary' => $this->availability->hospitalSummary($hospitalId),
        ]);
    }

    public function show(Request $request, Bed $bed): JsonResponse
    {
        $this->authorize('view', $bed);
        $bed->load(['category:id,name,slug,is_er', 'room.ward:id,name,ward_type', 'updater:id,name', 'activeReservation.referral:id,referral_number,status']);

        return $this->success($this->payload($bed));
    }

    public function updateStatus(Request $request, Bed $bed): JsonResponse
    {
        $this->authorize('updateStatus', $bed);

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
            'expected_updated_at' => ['nullable', 'date'],
        ]);

        try {
            $updated = $this->beds->updateStatus(
                $bed,
                $request->user(),
                $validated['status'],
                $validated['reason'] ?? null,
                ! empty($validated['expected_updated_at']) ? Carbon::parse($validated['expected_updated_at']) : null,
            );
        } catch (StaleBedUpdateException $exception) {
            return $this->error($exception->getMessage(), 409);
        } catch (ValidationException $exception) {
            return $this->error($exception->getMessage(), 422, $exception->errors());
        }

        $updated->load(['category:id,name,slug,is_er', 'room.ward:id,name,ward_type', 'updater:id,name']);

        return $this->success($this->payload($updated), 'Bed status updated.');
    }

    public function bulkStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hospital_id' => ['required', 'integer', 'exists:hospitals,id'],
            'bed_ids' => ['required', 'array', 'min:1', 'max:100'],
            'bed_ids.*' => ['integer', 'distinct'],
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $count = $this->beds->bulkUpdate(
                $request->user(),
                (int) $validated['hospital_id'],
                $validated['bed_ids'],
                $validated['status'],
                $validated['reason'] ?? null,
            );
        } catch (ValidationException $exception) {
            return $this->error($exception->getMessage(), 422, $exception->errors());
        }

        return $this->success(['updated' => $count], $count.' bed(s) updated.');
    }

    public function history(Request $request, Bed $bed): JsonResponse
    {
        $this->authorize('viewHistory', $bed);

        $history = BedStatusHistory::query()
            ->where('bed_id', $bed->id)
            ->with('changer:id,name')
            ->latest('changed_at')
            ->limit(100)
            ->get()
            ->map(fn (BedStatusHistory $entry) => [
                'id' => $entry->id,
                'old_status' => $entry->old_status,
                'new_status' => $entry->new_status,
                'reason' => $entry->reason,
                'changed_by' => $entry->changer?->name ?? 'System',
                'changed_at' => $entry->changed_at?->toIso8601String(),
            ]);

        return $this->success($history, 'Bed status history retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hospital_id' => ['required', 'integer', 'exists:hospitals,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'bed_category_id' => ['required', 'integer', 'exists:bed_categories,id'],
            'bed_code' => ['required', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $hospitalId = (int) $validated['hospital_id'];

        if (! $this->access->hasHospitalPermission($request->user(), $hospitalId, 'beds.manage')) {
            return $this->error('You are not allowed to manage inventory for this hospital.', 403);
        }

        $room = Room::query()->with('ward')->findOrFail($validated['room_id']);

        if ($room->ward?->hospital_id !== $hospitalId) {
            return $this->error('The selected room does not belong to this hospital.', 422);
        }

        if (Bed::withTrashed()->where('hospital_id', $hospitalId)->where('bed_code', $validated['bed_code'])->exists()) {
            return $this->error('That bed code already exists in this hospital.', 422);
        }

        $bed = DB::transaction(function () use ($validated, $request, $hospitalId) {
            $bed = Bed::create([
                'hospital_id' => $hospitalId,
                'room_id' => $validated['room_id'],
                'bed_category_id' => $validated['bed_category_id'],
                'bed_code' => strtoupper($validated['bed_code']),
                'status' => Bed::STATUS_AVAILABLE,
                'notes' => $validated['notes'] ?? null,
                'active' => true,
                'updated_by' => $request->user()->id,
            ]);

            BedStatusHistory::create([
                'bed_id' => $bed->id,
                'hospital_id' => $hospitalId,
                'old_status' => null,
                'new_status' => Bed::STATUS_AVAILABLE,
                'reason' => 'Bed registered',
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
            ]);

            $this->audit->record('bed.created', $bed, ['bed_code' => $bed->bed_code], $hospitalId, $request->user());

            return $bed;
        });

        $bed->load(['category:id,name,slug,is_er', 'room.ward:id,name,ward_type']);

        return $this->success($this->payload($bed), 'Bed registered.', 201);
    }

    public function toggle(Request $request, Bed $bed): JsonResponse
    {
        $this->authorize('update', $bed);

        if ($bed->activeReservation && $bed->active) {
            return $this->error('Release the active reservation before deactivating this bed.', 422);
        }

        $bed->forceFill(['active' => ! $bed->active, 'updated_by' => $request->user()->id])->save();
        $this->audit->record('bed.activation.changed', $bed, ['active' => $bed->active], $bed->hospital_id, $request->user());

        return $this->success(['active' => $bed->active], $bed->bed_code.' was '.($bed->active ? 'reactivated' : 'removed from the active board').'.');
    }

    private function payload(Bed $bed): array
    {
        return [
            'id' => $bed->id,
            'hospital_id' => $bed->hospital_id,
            'bed_code' => $bed->bed_code,
            'status' => $bed->status,
            'status_label' => $bed->statusLabel(),
            'active' => $bed->active,
            'notes' => $bed->notes,
            'category' => $bed->category ? [
                'id' => $bed->category->id,
                'name' => $bed->category->name,
                'slug' => $bed->category->slug,
                'is_er' => $bed->category->is_er,
            ] : null,
            'ward' => $bed->room?->ward ? [
                'id' => $bed->room->ward->id,
                'name' => $bed->room->ward->name,
                'ward_type' => $bed->room->ward->ward_type,
            ] : null,
            'room' => $bed->room?->room_code,
            'updated_at' => $bed->updated_at?->toIso8601String(),
            'updated_by' => $bed->updater?->name,
            'reservation' => $bed->activeReservation ? [
                'id' => $bed->activeReservation->id,
                'referral_id' => $bed->activeReservation->referral_id,
                'referral_number' => $bed->activeReservation->referral?->referral_number,
                'expires_at' => $bed->activeReservation->expires_at?->toIso8601String(),
            ] : null,
        ];
    }
}
