<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHospitalRequest;
use App\Http\Requests\UpdateHospitalRequest;
use App\Models\Hospital;
use App\Models\Municipality;
use App\Models\Service;
use App\Services\AuditLogger;
use App\Services\HospitalAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminHospitalController extends Controller
{
    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->access->loadContext($user);

        if (! $this->access->hasPermission($user, 'hospitals.manage') && ! $this->access->hasPermission($user, 'hospitals.register')) {
            abort(403, 'You do not have permission to manage hospital profiles.');
        }

        $hospitals = $this->access->managementHospitals($user)
            ->each(function (Hospital $hospital) {
                $hospital->loadCount(['beds', 'memberships']);
                $hospital->load('services:id');
            })
            ->map(fn (Hospital $hospital) => [
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
                'municipality_id' => $hospital->municipality_id,
                'municipality' => $hospital->municipality?->name,
                'province' => $hospital->province?->name,
                'region' => $hospital->region?->short_name,
                'location' => $hospital->location_label,
                'beds_count' => $hospital->beds_count,
                'users_count' => $hospital->memberships_count,
                'service_ids' => $hospital->services->pluck('id')->values(),
                'profile_updated_at' => $hospital->profile_updated_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('Admin/Hospitals/Index', [
            'hospitals' => $hospitals,
            'municipalities' => Municipality::query()
                ->with('province:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'province_id']),
            'services' => Service::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'canCreate' => $this->access->hasPermission($user, 'hospitals.register'),
            'isNetworkUser' => $this->access->isNetworkUser($user),
        ]);
    }

    public function store(StoreHospitalRequest $request): RedirectResponse
    {
        $this->authorize('create', Hospital::class);
        $validated = $request->validated();

        $hospital = DB::transaction(function () use ($validated, $request) {
            $services = $validated['services'] ?? [];
            unset($validated['services']);

            $hospital = Hospital::create([
                ...$validated,
                'profile_updated_at' => now(),
            ]);

            $hospital->services()->sync($services);

            $this->audit->record('hospital.registered', $hospital, [
                'hospital_code' => $hospital->hospital_code,
                'name' => $hospital->name,
            ], $hospital->id, $request->user());

            return $hospital;
        });

        return redirect()->route('admin.hospitals.index')->with('success', $hospital->name.' was registered in the network.');
    }

    public function update(UpdateHospitalRequest $request, Hospital $hospital): RedirectResponse
    {
        $this->authorize('update', $hospital);
        $validated = $request->validated();

        if (! $this->access->isNetworkUser($request->user())) {
            $validated = Arr::except($validated, ['hospital_code', 'ownership_type', 'status', 'municipality_id']);
        }

        $services = $validated['services'] ?? null;
        unset($validated['services']);

        DB::transaction(function () use ($hospital, $validated, $services, $request) {
            $before = $hospital->only(array_keys($validated));
            $hospital->fill($validated);
            $changed = $hospital->getDirty();
            $hospital->forceFill(['profile_updated_at' => now()])->save();

            if ($services !== null) {
                $hospital->services()->sync($services);
            }

            $this->audit->record('hospital.profile.updated', $hospital, [
                'before' => $before,
                'after' => $changed,
                'services_synced' => $services !== null,
            ], $hospital->id, $request->user());
        });

        return back()->with('success', 'Hospital profile updated. Changes are recorded in the audit trail.');
    }
}
