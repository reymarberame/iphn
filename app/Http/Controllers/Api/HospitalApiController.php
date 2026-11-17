<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Requests\StoreHospitalRequest;
use App\Http\Requests\UpdateHospitalRequest;
use App\Models\Hospital;
use App\Models\Municipality;
use App\Models\Service;
use App\Services\AuditLogger;
use App\Services\BedAvailabilityService;
use App\Services\HospitalAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class HospitalApiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly BedAvailabilityService $availability,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'region_id', 'province_id', 'municipality_id', 'ownership_type',
            'category', 'service_id', 'status', 'facility_type',
        ]);

        $hospitals = $this->availability->directory($request->user(), $filters)->values();

        return $this->success($hospitals, 'Authorized hospitals retrieved.', 200, ['count' => $hospitals->count()]);
    }

    public function show(Request $request, Hospital $hospital): JsonResponse
    {
        $this->authorize('view', $hospital);

        $hospital->load(['municipality.province.region', 'services']);

        return $this->success([
            'id' => $hospital->id,
            'hospital_code' => $hospital->hospital_code,
            'name' => $hospital->name,
            'short_name' => $hospital->short_name,
            'facility_type' => $hospital->facility_type,
            'ownership_type' => $hospital->ownership_type,
            'ownership_label' => $hospital->ownershipLabel(),
            'status' => $hospital->status,
            'status_label' => $hospital->statusLabel(),
            'address' => $hospital->address,
            'phone' => $hospital->phone,
            'email' => $hospital->email,
            'bed_capacity' => $hospital->bed_capacity,
            'notes' => $hospital->notes,
            'municipality' => $hospital->municipality?->name,
            'province' => $hospital->province?->name,
            'region' => $hospital->region?->short_name,
            'services' => $hospital->services->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'availability_notes' => $service->pivot?->availability_notes,
            ]),
            'availability' => $this->availability->hospitalAvailability($hospital),
        ]);
    }

    public function store(StoreHospitalRequest $request): JsonResponse
    {
        $this->authorize('create', Hospital::class);
        $validated = $request->validated();

        $hospital = DB::transaction(function () use ($validated, $request) {
            $services = $validated['services'] ?? [];
            unset($validated['services']);

            $hospital = Hospital::create([...$validated, 'profile_updated_at' => now()]);
            $hospital->services()->sync($services);

            $this->audit->record('hospital.registered', $hospital, [
                'hospital_code' => $hospital->hospital_code,
                'name' => $hospital->name,
            ], $hospital->id, $request->user());

            return $hospital;
        });

        return $this->success($hospital->load('municipality.province.region'), 'Hospital registered.', 201);
    }

    public function update(UpdateHospitalRequest $request, Hospital $hospital): JsonResponse
    {
        $this->authorize('update', $hospital);
        $validated = $request->validated();

        if (! $this->access->isNetworkUser($request->user())) {
            $validated = Arr::except($validated, ['hospital_code', 'ownership_type', 'status', 'municipality_id']);
        }

        $services = $validated['services'] ?? null;
        unset($validated['services']);

        DB::transaction(function () use ($hospital, $validated, $services, $request) {
            $hospital->fill($validated)->save();
            $hospital->forceFill(['profile_updated_at' => now()])->save();

            if ($services !== null) {
                $hospital->services()->sync($services);
            }

            $this->audit->record('hospital.profile.updated', $hospital, ['changes' => $validated], $hospital->id, $request->user());
        });

        return $this->success($hospital->fresh(['municipality.province.region', 'services']), 'Hospital profile updated.');
    }

    public function services(Hospital $hospital): JsonResponse
    {
        $this->authorize('view', $hospital);

        return $this->success($hospital->services()->get());
    }

    public function availability(Request $request, Hospital $hospital): JsonResponse
    {
        $this->authorize('view', $hospital);

        return $this->success($this->availability->hospitalAvailability($hospital));
    }

    public function municipalities(): JsonResponse
    {
        return $this->success(Municipality::query()->with('province:id,name,region_id')->orderBy('name')->get());
    }

    public function serviceOptions(): JsonResponse
    {
        return $this->success(Service::query()->where('active', true)->orderBy('name')->get());
    }
}
