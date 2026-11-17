<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Services\BedAvailabilityService;
use App\Services\HospitalAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HospitalController extends Controller
{
    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly BedAvailabilityService $availability,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only([
            'search', 'region_id', 'province_id', 'ownership_type', 'category', 'service_id', 'status',
        ]);

        $hospitals = $this->availability->directory($request->user(), $filters);
        $referring = $this->referringHospitals($request->user());

        $hospitals = $hospitals->map(function (array $hospital) use ($referring) {
            $hospital['can_refer_here'] = $referring->contains(fn (Hospital $item) => $item->id !== $hospital['hospital_id']);

            return $hospital;
        });

        return Inertia::render('Hospitals/Index', [
            'hospitals' => $hospitals->values(),
            'filters' => $filters,
            'referringHospitals' => $referring->map(fn (Hospital $hospital) => [
                'id' => $hospital->id,
                'name' => $hospital->name,
                'short_name' => $hospital->short_name,
            ])->values(),
        ]);
    }

    public function show(Request $request, Hospital $hospital): Response
    {
        $this->authorize('view', $hospital);

        $hospital->load(['municipality.province.region', 'services']);
        $availability = $this->availability->hospitalAvailability($hospital);
        $referring = $this->referringHospitals($request->user())
            ->reject(fn (Hospital $item) => $item->id === $hospital->id)
            ->values();

        return Inertia::render('Hospitals/Show', [
            'hospital' => [
                'id' => $hospital->id,
                'name' => $hospital->name,
                'short_name' => $hospital->short_name,
                'hospital_code' => $hospital->hospital_code,
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
                ])->values(),
            ],
            'availability' => $availability,
            'referringHospitals' => $referring->map(fn (Hospital $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'short_name' => $item->short_name,
            ])->values(),
            'canManageBeds' => $this->access->hasHospitalPermission($request->user(), $hospital->id, 'beds.manage'),
        ]);
    }

    private function referringHospitals($user)
    {
        return $this->access->activeMemberships($user)
            ->filter(fn ($membership) => $membership->role?->permissions->contains('name', 'referrals.create'))
            ->map(fn ($membership) => $membership->hospital)
            ->filter()
            ->unique('id')
            ->values();
    }
}
