<?php

namespace App\Http\Controllers;

use App\Models\AdminScope;
use App\Models\HospitalUserMembership;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\HospitalAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $actor = $request->user();
        $this->access->loadContext($actor);
        $this->authorize('viewAny', User::class);

        $isNetwork = $this->access->isNetworkUser($actor);
        $hospitalIds = $this->access->managementHospitalIds($actor);

        $query = User::query()
            ->with([
                'roles:id,name,slug,scope_type',
                'memberships.role:id,name,slug,scope_type',
                'memberships.hospital:id,name,short_name',
                'adminScopes.region:id,name,short_name',
                'adminScopes.province:id,name',
            ])
            ->when($request->input('search'), function ($builder, $search) {
                $builder->where(fn ($inner) => $inner
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%'));
            })
            ->when($request->input('status'), fn ($builder, $status) => $builder->where('status', $status))
            ->when($request->input('role_id'), function ($builder, $roleId) {
                $builder->where(fn ($inner) => $inner
                    ->whereHas('roles', fn ($role) => $role->where('roles.id', $roleId))
                    ->orWhereHas('memberships', fn ($membership) => $membership->where('role_id', $roleId)));
            });

        if (! $isNetwork) {
            $query->whereHas('memberships', fn ($builder) => $builder->whereIn('hospital_id', $hospitalIds));
        }

        $users = $query->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'global_roles' => $user->roles->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'scope_type' => $role->scope_type,
                ])->values(),
                'memberships' => $user->memberships
                    ->filter(fn ($membership) => $isNetwork || in_array($membership->hospital_id, $hospitalIds, true))
                    ->map(fn ($membership) => [
                        'id' => $membership->id,
                        'hospital_id' => $membership->hospital_id,
                        'hospital' => $membership->hospital?->short_name ?? $membership->hospital?->name,
                        'role_id' => $membership->role_id,
                        'role' => $membership->role?->name,
                        'status' => $membership->status,
                    ])->values(),
                'admin_scopes' => $user->adminScopes->map(fn (AdminScope $scope) => [
                    'region_id' => $scope->region_id,
                    'province_id' => $scope->province_id,
                    'region' => $scope->region?->short_name ?? $scope->region?->name,
                    'province' => $scope->province?->name,
                ])->values(),
                'is_self' => $user->id === $actor->id,
            ]);

        $roles = Role::query()
            ->when(! $isNetwork, fn ($builder) => $builder->where('scope_type', 'hospital'))
            ->orderBy('scope_type')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'scope_type', 'description']);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'status', 'role_id']),
            'roles' => $roles,
            'hospitals' => $this->access->managementHospitals($actor)
                ->map(fn ($hospital) => ['id' => $hospital->id, 'name' => $hospital->name, 'short_name' => $hospital->short_name])
                ->values(),
            'regions' => \App\Models\Region::query()->orderBy('name')->get(['id', 'name', 'short_name']),
            'provinces' => \App\Models\Province::query()->orderBy('name')->get(['id', 'name', 'region_id']),
            'isNetworkUser' => $isNetwork,
            'isSuperAdmin' => $this->access->isSuperAdmin($actor),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $this->authorize('create', User::class);

        $validated = $this->validateUser($request);
        $role = Role::query()->findOrFail($validated['role_id']);
        $this->assertCanAssignRole($actor, $role, $validated, $request);
        $this->assertScopeWithinActor($actor, $role, $validated);

        $user = DB::transaction(function () use ($validated, $role, $actor) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'phone' => $validated['phone'] ?? null,
                'status' => $validated['status'],
            ]);

            $this->syncAssignment($user, $role, $validated);

            $this->audit->record('user.created', $user, [
                'email' => $user->email,
                'role' => $role->slug,
                'hospital_id' => $validated['hospital_id'] ?? null,
            ], $validated['hospital_id'] ?? null, $actor);

            return $user;
        });

        return back()->with('success', $user->name.' was added with the '.$role->name.' role.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->authorize('update', $user);

        $validated = $this->validateUser($request, $user);
        $role = ! empty($validated['role_id']) ? Role::query()->findOrFail($validated['role_id']) : null;

        if ($validated['status'] !== 'active' && $actor->id === $user->id) {
            throw ValidationException::withMessages(['status' => 'You cannot suspend or deactivate your own account.']);
        }

        if ($actor->id !== $user->id) {
            if (! $role) {
                throw ValidationException::withMessages(['role_id' => 'Select a role for this user.']);
            }

            $this->assertCanAssignRole($actor, $role, $validated, $request);
            $this->assertScopeWithinActor($actor, $role, $validated);

            if ($role->slug === 'super_admin' && ! $this->access->isSuperAdmin($actor)) {
                throw ValidationException::withMessages(['role_id' => 'Only a Super Administrator can assign this role.']);
            }
        }

        DB::transaction(function () use ($user, $validated, $role, $actor) {
            $user->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'status' => $validated['status'],
            ]);

            if (! empty($validated['password'])) {
                $user->password = $validated['password'];
            }

            $user->save();

            if ($actor->id !== $user->id && $role) {
                $this->syncAssignment($user, $role, $validated);
            }

            $this->audit->record('user.updated', $user, [
                'email' => $user->email,
                'status' => $user->status,
                'role' => $role?->slug,
                'hospital_id' => $validated['hospital_id'] ?? null,
                'self_service' => $actor->id === $user->id,
            ], $validated['hospital_id'] ?? null, $actor);
        });

        return back()->with('success', $user->name.' was updated.');
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive'])],
            'role_id' => [$user ? 'nullable' : 'required', 'integer', 'exists:roles,id'],
            'hospital_id' => ['nullable', 'integer', 'exists:hospitals,id'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
        ]);
    }

    private function assertCanAssignRole(User $actor, Role $role, array $validated, Request $request): void
    {
        if (! $this->access->isNetworkUser($actor)) {
            if ($role->isNetworkScoped()) {
                throw ValidationException::withMessages(['role_id' => 'Hospital administrators can only assign hospital-scoped roles.']);
            }

            if (empty($validated['hospital_id']) || ! in_array((int) $validated['hospital_id'], $this->access->managementHospitalIds($actor), true)) {
                throw ValidationException::withMessages(['hospital_id' => 'Select a hospital within your administration scope.']);
            }

            if (! $this->access->hasHospitalPermission($actor, (int) $validated['hospital_id'], 'users.manage')) {
                throw ValidationException::withMessages(['hospital_id' => 'You cannot manage users for that hospital.']);
            }

            return;
        }

        if ($role->slug === 'super_admin' && ! $this->access->isSuperAdmin($actor)) {
            throw ValidationException::withMessages(['role_id' => 'Only a Super Administrator can assign this role.']);
        }

        if (! $role->isNetworkScoped() && empty($validated['hospital_id'])) {
            throw ValidationException::withMessages(['hospital_id' => 'Hospital-scoped roles require a hospital assignment.']);
        }
    }

    private function assertScopeWithinActor(User $actor, Role $role, array $validated): void
    {
        if (! $this->access->isNetworkUser($actor) || $this->access->isSuperAdmin($actor) || $actor->adminScopes->isEmpty()) {
            return;
        }

        $allowedRegions = $actor->adminScopes->pluck('region_id')->filter();
        $allowedProvinces = $actor->adminScopes->pluck('province_id')->filter();

        if ($role->scope_type === 'region') {
            if (empty($validated['region_id']) || ! $allowedRegions->contains((int) $validated['region_id'])) {
                throw ValidationException::withMessages(['region_id' => 'Choose a region inside your own administrative scope.']);
            }
        }

        if ($role->scope_type === 'province') {
            if (empty($validated['province_id']) || (! $allowedProvinces->contains((int) $validated['province_id']) && ! $allowedRegions->contains((int) ($validated['region_id'] ?? 0)))) {
                throw ValidationException::withMessages(['province_id' => 'Choose a province inside your own administrative scope.']);
            }
        }
    }

    private function syncAssignment(User $user, Role $role, array $validated): void
    {
        $user->roles()->detach();
        $user->memberships()->delete();
        $user->adminScopes()->delete();

        if ($role->isNetworkScoped()) {
            $user->roles()->attach($role->id);

            if ($role->scope_type === 'region' && ! empty($validated['region_id'])) {
                $user->adminScopes()->create(['region_id' => $validated['region_id']]);
            } elseif ($role->scope_type === 'province' && ! empty($validated['province_id'])) {
                $user->adminScopes()->create(['province_id' => $validated['province_id']]);
            }

            return;
        }

        HospitalUserMembership::create([
            'user_id' => $user->id,
            'hospital_id' => $validated['hospital_id'],
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }
}
