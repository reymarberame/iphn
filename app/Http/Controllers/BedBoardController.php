<?php

namespace App\Http\Controllers;

use App\Exceptions\StaleBedUpdateException;
use App\Models\Bed;
use App\Models\BedCategory;
use App\Models\BedStatusHistory;
use App\Models\Hospital;
use App\Models\Room;
use App\Models\Ward;
use App\Services\AuditLogger;
use App\Services\BedAvailabilityService;
use App\Services\BedManagementService;
use App\Services\HospitalAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BedBoardController extends Controller
{
    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly BedAvailabilityService $availability,
        private readonly BedManagementService $beds,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $hospitals = $this->access->managementHospitals($user);

        if ($hospitals->isEmpty()) {
            abort(403, 'No hospital is assigned to your account.');
        }

        $hospitalId = (int) $request->query('hospital', $hospitals->first()->id);

        if (! $hospitals->contains('id', $hospitalId)) {
            abort(403, 'You do not have access to that hospital bed board.');
        }

        $hospital = $hospitals->firstWhere('id', $hospitalId);
        $filters = $request->only(['ward_id', 'category', 'status', 'search']);

        $bedsQuery = Bed::query()
            ->where('hospital_id', $hospitalId)
            ->where('active', true)
            ->with([
                'room.ward:id,hospital_id,name,ward_type',
                'category:id,name,slug,is_er',
                'updater:id,name',
                'activeReservation.referral:id,referral_number,status',
            ])
            ->withCount(['statusHistory'])
            ->when($filters['ward_id'] ?? null, fn ($query, $wardId) => $query->whereHas('room', fn ($room) => $room->where('ward_id', $wardId)))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->whereHas('category', fn ($cat) => $cat->where('slug', $category)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('bed_code', 'like', '%'.$search.'%'))
            ->orderBy('bed_code');

        $beds = $bedsQuery->get()->map(fn (Bed $bed) => [
            'id' => $bed->id,
            'bed_code' => $bed->bed_code,
            'status' => $bed->status,
            'status_label' => $bed->statusLabel(),
            'category' => [
                'id' => $bed->category?->id,
                'name' => $bed->category?->name,
                'slug' => $bed->category?->slug,
                'is_er' => $bed->category?->is_er,
            ],
            'ward' => [
                'id' => $bed->room?->ward?->id,
                'name' => $bed->room?->ward?->name,
                'ward_type' => $bed->room?->ward?->ward_type,
            ],
            'room' => $bed->room?->room_code,
            'notes' => $bed->notes,
            'updated_at' => $bed->updated_at?->toIso8601String(),
            'updated_by' => $bed->updater?->name,
            'history_count' => $bed->status_history_count,
            'reservation' => $bed->activeReservation ? [
                'id' => $bed->activeReservation->id,
                'referral_id' => $bed->activeReservation->referral_id,
                'referral_number' => $bed->activeReservation->referral?->referral_number,
                'expires_at' => $bed->activeReservation->expires_at?->toIso8601String(),
            ] : null,
        ]);

        $wards = Ward::query()
            ->where('hospital_id', $hospitalId)
            ->with(['rooms' => fn ($query) => $query->where('active', true)->orderBy('room_code')])
            ->orderBy('name')
            ->get()
            ->map(fn (Ward $ward) => [
                'id' => $ward->id,
                'name' => $ward->name,
                'ward_type' => $ward->ward_type,
                'floor' => $ward->floor,
                'rooms' => $ward->rooms->map(fn (Room $room) => [
                    'id' => $room->id,
                    'room_code' => $room->room_code,
                    'name' => $room->name,
                ])->values(),
            ]);

        $grouped = $beds->groupBy(fn ($bed) => $bed['ward']['id'] ?? 0)
            ->map(function ($wardBeds, $wardId) {
                $ward = $wardBeds->first()['ward'];

                return [
                    'id' => $wardId,
                    'name' => $ward['name'] ?? 'Unassigned',
                    'ward_type' => $ward['ward_type'] ?? null,
                    'beds' => $wardBeds->values(),
                ];
            })
            ->values();

        $summary = $this->availability->hospitalSummary($hospital);

        return Inertia::render('Beds/Index', [
            'hospital' => [
                'id' => $hospital->id,
                'name' => $hospital->name,
                'short_name' => $hospital->short_name,
                'hospital_code' => $hospital->hospital_code,
                'location' => $hospital->location_label,
            ],
            'hospitals' => $hospitals->map(fn (Hospital $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'short_name' => $item->short_name,
                'location' => $item->location_label,
            ])->values(),
            'summary' => $summary,
            'wards' => $grouped,
            'inventory' => $wards,
            'categories' => BedCategory::query()->where('active', true)->orderBy('sort_order')->get(['id', 'name', 'slug', 'is_er']),
            'filters' => $filters,
            'canUpdate' => $this->access->hasHospitalPermission($user, $hospitalId, 'beds.update'),
            'canManage' => $this->access->hasHospitalPermission($user, $hospitalId, 'beds.manage'),
            'bedCount' => $beds->count(),
        ]);
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bed_id' => ['required', 'integer', 'exists:beds,id'],
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
            'expected_updated_at' => ['nullable', 'date'],
        ]);

        $bed = Bed::query()->findOrFail($validated['bed_id']);
        $this->authorize('updateStatus', $bed);

        try {
            $updated = $this->beds->updateStatus(
                $bed,
                $request->user(),
                $validated['status'],
                $validated['reason'] ?? null,
                ! empty($validated['expected_updated_at']) ? Carbon::parse($validated['expected_updated_at']) : null,
            );
        } catch (StaleBedUpdateException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $counts = $this->availability->statusCounts($bed->hospital_id);

        return back()->with('success', $updated->bed_code.' is now '.$updated->statusLabel().'. '.$counts['available'].' beds are currently reported available.');
    }

    public function bulkStatus(Request $request): RedirectResponse
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
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', $count.' bed'.($count === 1 ? '' : 's').' updated to '.Bed::STATUS_LABELS[$validated['status']].'.');
    }

    public function history(Request $request, Bed $bed): JsonResponse
    {
        $this->authorize('viewHistory', $bed);

        $history = BedStatusHistory::query()
            ->where('bed_id', $bed->id)
            ->with('changer:id,name')
            ->latest('changed_at')
            ->limit(50)
            ->get()
            ->map(fn (BedStatusHistory $item) => [
                'id' => $item->id,
                'old_status' => $item->old_status,
                'new_status' => $item->new_status,
                'reason' => $item->reason,
                'changed_by' => $item->changer?->name ?? 'System',
                'changed_at' => $item->changed_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $history]);
    }

    public function storeBed(Request $request): RedirectResponse
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
            abort(403, 'You are not allowed to manage inventory for this hospital.');
        }

        $room = Room::query()->with('ward')->findOrFail($validated['room_id']);

        if ($room->ward?->hospital_id !== $hospitalId) {
            throw ValidationException::withMessages(['room_id' => 'The selected room does not belong to this hospital.']);
        }

        if (Bed::withTrashed()->where('hospital_id', $hospitalId)->where('bed_code', $validated['bed_code'])->exists()) {
            throw ValidationException::withMessages(['bed_code' => 'That bed code already exists in this hospital.']);
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

        return back()->with('success', $bed->bed_code.' was added to the inventory as available.');
    }

    public function storeWard(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hospital_id' => ['required', 'integer', 'exists:hospitals,id'],
            'name' => ['required', 'string', 'max:100'],
            'ward_type' => ['required', 'string', 'max:30'],
            'floor' => ['nullable', 'string', 'max:30'],
        ]);

        if (! $this->access->hasHospitalPermission($request->user(), (int) $validated['hospital_id'], 'beds.manage')) {
            abort(403);
        }

        $ward = Ward::create([...$validated, 'active' => true]);
        $this->audit->record('ward.created', $ward, ['name' => $ward->name], $ward->hospital_id, $request->user());

        return back()->with('success', $ward->name.' was added. Add a room next.');
    }

    public function storeRoom(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ward_id' => ['required', 'integer', 'exists:wards,id'],
            'room_code' => ['required', 'string', 'max:30'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        $ward = Ward::query()->findOrFail($validated['ward_id']);

        if (! $this->access->hasHospitalPermission($request->user(), $ward->hospital_id, 'beds.manage')) {
            abort(403);
        }

        $room = Room::create([...$validated, 'active' => true]);
        $this->audit->record('room.created', $room, ['room_code' => $room->room_code], $ward->hospital_id, $request->user());

        return back()->with('success', 'Room '.$room->room_code.' was added to '.$ward->name.'.');
    }

    public function toggleBed(Request $request, Bed $bed): RedirectResponse
    {
        $this->authorize('update', $bed);

        if ($bed->activeReservation && $bed->active) {
            return back()->with('error', 'Release the active reservation before deactivating this bed.');
        }

        $bed->forceFill(['active' => ! $bed->active, 'updated_by' => $request->user()->id])->save();
        $this->audit->record('bed.activation.changed', $bed, ['active' => $bed->active], $bed->hospital_id, $request->user());

        return back()->with('success', $bed->bed_code.' was '.($bed->active ? 'reactivated' : 'removed from the active board').'.');
    }
}
