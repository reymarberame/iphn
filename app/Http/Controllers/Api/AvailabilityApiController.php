<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Models\Hospital;
use App\Services\AuditLogger;
use App\Services\BedAvailabilityService;
use App\Services\HospitalAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityApiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BedAvailabilityService $availability,
        private readonly HospitalAccessService $access,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'region_id', 'province_id', 'municipality_id', 'ownership_type',
            'category', 'service_id', 'status', 'hospital_id',
        ]);

        $rows = $this->availability->availabilityRows($request->user(), $filters);

        return $this->success($rows, 'Availability search results.', 200, ['count' => count($rows)]);
    }

    public function summary(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'region_id', 'province_id', 'ownership_type', 'category', 'service_id',
        ]);

        return $this->success($this->availability->networkSummary($request->user(), $filters));
    }

    public function snapshots(Request $request): JsonResponse
    {
        if (! $this->access->hasPermission($request->user(), 'reports.national')
            && ! $this->access->hasPermission($request->user(), 'settings.manage')) {
            abort(403, 'Snapshot generation requires a network reporting permission.');
        }

        $ids = $this->access->managementHospitalIds($request->user());
        $created = [];

        foreach (Hospital::query()->whereIn('id', $ids)->orderBy('name')->get() as $hospital) {
            $snapshot = $this->availability->captureSnapshot($hospital);
            $created[] = [
                'id' => $snapshot->id,
                'hospital_id' => $hospital->id,
                'hospital_name' => $hospital->name,
                'total_beds' => $snapshot->total_beds,
                'available_beds' => $snapshot->available_beds,
                'captured_at' => $snapshot->captured_at?->toIso8601String(),
            ];
        }

        $this->audit->record('availability.snapshot.generated', null, ['hospitals' => count($created)], null, $request->user());

        return $this->success($created, 'Availability snapshots captured.', 201);
    }
}
