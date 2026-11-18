<?php

namespace App\Services;

use App\Models\AdminScope;
use App\Models\Hospital;
use App\Models\HospitalUserMembership;
use App\Models\Referral;
use App\Models\TransferRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HospitalAccessService
{
    /** @var array<int, array<string, mixed>> */
    private array $contextCache = [];

    public function loadContext(User $user): User
    {
        return $user->loadMissing([
            'roles.permissions',
            'memberships.role.permissions',
            'memberships.hospital.municipality.province.region',
            'adminScopes.region',
            'adminScopes.province',
        ]);
    }

    public function hasPermission(User $user, string $permission): bool
    {
        $this->loadContext($user);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $global = $user->roles->flatMap(fn ($role) => $role->permissions->pluck('name'));

        $hospital = $user->memberships
            ->where('status', 'active')
            ->flatMap(fn ($membership) => $membership->role?->permissions->pluck('name') ?? collect());

        return $global->merge($hospital)->contains($permission);
    }

    public function isSuperAdmin(User $user): bool
    {
        $this->loadContext($user);

        return $user->roles->contains('slug', 'super_admin');
    }

    public function isNetworkUser(User $user): bool
    {
        $this->loadContext($user);

        return $user->roles->contains(fn ($role) => in_array($role->scope_type, ['network', 'region', 'province'], true));
    }

    public function roleSlugs(User $user): array
    {
        $this->loadContext($user);

        return $user->roles->pluck('slug')
            ->merge($user->memberships->where('status', 'active')->map(fn ($m) => $m->role?->slug))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function roleNames(User $user): array
    {
        $this->loadContext($user);

        return $user->roles->pluck('name')
            ->merge($user->memberships->where('status', 'active')->map(fn ($m) => $m->role?->name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function activeMemberships(User $user): Collection
    {
        $this->loadContext($user);

        return $user->memberships->where('status', 'active');
    }

    public function primaryMembership(User $user): ?HospitalUserMembership
    {
        return $this->activeMemberships($user)->first();
    }

    public function hasActiveMembership(User $user, int $hospitalId): bool
    {
        return $this->activeMemberships($user)->contains('hospital_id', $hospitalId);
    }

    /**
     * Hospitals the user may manage (bed board, users, referrals they own).
     */
    public function managementHospitalIds(User $user): array
    {
        $this->loadContext($user);

        if ($this->isNetworkUser($user)) {
            return $this->scopeHospitalsQuery($user)->pluck('id')->all();
        }

        return $this->activeMemberships($user)->pluck('hospital_id')->unique()->values()->all();
    }

    /**
     * Hospitals where the user holds an active membership. Patient-linked
     * records (referrals, transfers, reservations) are restricted to these
     * even when the user also carries a network/region/province role.
     */
    public function patientLinkedHospitalIds(User $user): array
    {
        return $this->activeMemberships($user)
            ->pluck('hospital_id')
            ->unique()
            ->values()
            ->all();
    }

    public function managementHospitals(User $user): Collection
    {
        $this->loadContext($user);

        if ($this->isNetworkUser($user)) {
            return $this->scopeHospitalsQuery($user)
                ->with('municipality.province.region')
                ->orderBy('name')
                ->get();
        }

        $ids = $this->managementHospitalIds($user);

        return Hospital::query()
            ->whereIn('id', $ids)
            ->with('municipality.province.region')
            ->orderBy('name')
            ->get();
    }

    /**
     * Hospitals visible in the directory / availability dashboard.
     * Hospital staff can browse the network to identify receiving facilities.
     */
    public function directoryHospitalsQuery(User $user): Builder
    {
        $query = Hospital::query();

        if ($this->isNetworkUser($user)) {
            $query = $this->applyGeographicScope($user, $query);
        }

        return $query;
    }

    public function scopeHospitalsQuery(User $user): Builder
    {
        return $this->applyGeographicScope($user, Hospital::query());
    }

    private function applyGeographicScope(User $user, Builder $query): Builder
    {
        $scopes = $user->adminScopes;

        if (! $this->isNetworkUser($user) || $scopes->isEmpty() || $this->isSuperAdmin($user)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($scopes) {
            foreach ($scopes as $scope) {
                $this->applyScopeClause($q, $scope);
            }
        });
    }

    private function applyScopeClause(Builder $q, AdminScope $scope): void
    {
        if ($scope->province_id) {
            $q->orWhereHas('municipality', fn (Builder $m) => $m->where('province_id', $scope->province_id));
        } elseif ($scope->region_id) {
            $q->orWhereHas('municipality.province', fn (Builder $p) => $p->where('region_id', $scope->region_id));
        } else {
            $q->orWhereRaw('1 = 1');
        }
    }

    public function canViewHospital(User $user, Hospital $hospital): bool
    {
        if ($hospital->trashed()) {
            return false;
        }

        if (! $this->isNetworkUser($user)) {
            return true;
        }

        return $this->scopeHospitalsQuery($user)->whereKey($hospital->id)->exists();
    }

    public function canManageHospital(User $user, int $hospitalId): bool
    {
        return in_array($hospitalId, $this->managementHospitalIds($user), true);
    }

    /**
     * Permission check scoped to a single hospital, derived from memberships
     * or (for network users) their geographic scope and role permissions.
     */
    public function hasHospitalPermission(User $user, int $hospitalId, string $permission): bool
    {
        $this->loadContext($user);

        if ($this->isNetworkUser($user)) {
            return $this->canManageHospital($user, $hospitalId)
                && $this->hasPermission($user, $permission);
        }

        $membership = $this->activeMemberships($user)->firstWhere('hospital_id', $hospitalId);

        if (! $membership) {
            return false;
        }

        return $membership->role?->permissions->contains('name', $permission) ?? false;
    }

    public function canCreateReferralFrom(User $user, int $hospitalId): bool
    {
        return $this->hasActiveMembership($user, $hospitalId)
            && $this->hasHospitalPermission($user, $hospitalId, 'referrals.create');
    }

    public function canViewReferral(User $user, Referral $referral): bool
    {
        if (! $this->hasPermission($user, 'referrals.view')) {
            return false;
        }

        return $this->hasActiveMembership($user, $referral->referring_hospital_id)
            || $this->hasActiveMembership($user, $referral->receiving_hospital_id);
    }

    public function canRespondToReferral(User $user, Referral $referral): bool
    {
        return $this->hasActiveMembership($user, $referral->receiving_hospital_id)
            && $this->hasHospitalPermission($user, $referral->receiving_hospital_id, 'referrals.respond');
    }

    public function referralAbilities(User $user, Referral $referral): array
    {
        if (! $this->canViewReferral($user, $referral)) {
            return [];
        }

        $isReferring = $this->hasActiveMembership($user, $referral->referring_hospital_id);
        $isReceiving = $this->hasActiveMembership($user, $referral->receiving_hospital_id);
        $canRespond = $isReceiving && $this->hasHospitalPermission($user, $referral->receiving_hospital_id, 'referrals.respond');
        $canCoordinate = $isReferring && $this->hasHospitalPermission($user, $referral->referring_hospital_id, 'referrals.create');
        $canTransfer = $isReferring && $this->hasHospitalPermission($user, $referral->referring_hospital_id, 'transfers.manage');

        return [
            'is_referring' => $isReferring,
            'is_receiving' => $isReceiving,
            'can_submit' => $referral->status === Referral::STATUS_DRAFT && $canCoordinate,
            'can_cancel' => in_array($referral->status, [
                Referral::STATUS_DRAFT, Referral::STATUS_SUBMITTED, Referral::STATUS_UNDER_REVIEW,
                Referral::STATUS_MORE_INFO, Referral::STATUS_ACCEPTED, Referral::STATUS_TRANSFER_SCHEDULED,
            ], true) && ($canCoordinate || $canRespond),
            'can_accept' => $canRespond && in_array($referral->status, [
                Referral::STATUS_SUBMITTED, Referral::STATUS_UNDER_REVIEW, Referral::STATUS_MORE_INFO,
            ], true),
            'can_decline' => $canRespond && in_array($referral->status, [
                Referral::STATUS_SUBMITTED, Referral::STATUS_UNDER_REVIEW, Referral::STATUS_MORE_INFO,
            ], true),
            'can_request_information' => $canRespond && in_array($referral->status, [
                Referral::STATUS_SUBMITTED, Referral::STATUS_UNDER_REVIEW,
            ], true),
            'can_reserve_bed' => $canRespond
                && $this->hasHospitalPermission($user, $referral->receiving_hospital_id, 'beds.reserve')
                && $referral->status === Referral::STATUS_ACCEPTED
                && ! $referral->activeReservation,
            'can_schedule_transfer' => $canTransfer && $referral->status === Referral::STATUS_ACCEPTED && ! $referral->transfer,
            'can_record_departure' => $canTransfer && $referral->transfer
                && in_array($referral->transfer->status, [TransferRecord::STATUS_SCHEDULED, TransferRecord::STATUS_READY], true),
            'can_confirm_arrival' => $isReceiving && $referral->transfer
                && in_array($referral->transfer->status, [TransferRecord::STATUS_SCHEDULED, TransferRecord::STATUS_IN_TRANSIT], true),
            'can_complete_transfer' => $isReceiving && $referral->transfer
                && $referral->transfer->status === TransferRecord::STATUS_ARRIVED,
        ];
    }

    public function usersWithHospitalPermission(int $hospitalId, string $permission): Collection
    {
        return HospitalUserMembership::query()
            ->where('hospital_id', $hospitalId)
            ->where('status', 'active')
            ->whereHas('user', fn (Builder $q) => $q->where('status', 'active'))
            ->whereHas('role.permissions', fn (Builder $q) => $q->where('name', $permission))
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->values();
    }

    public function scopeLabel(User $user): string
    {
        $this->loadContext($user);

        if (! $this->isNetworkUser($user)) {
            $hospital = $this->activeMemberships($user)->first()?->hospital;

            return $hospital?->short_name ?: ($hospital?->name ?? 'Unassigned hospital');
        }

        $scopes = $user->adminScopes;

        if ($scopes->isEmpty() || $this->isSuperAdmin($user)) {
            return 'Network scope';
        }

        $labels = $scopes->map(function (AdminScope $scope) {
            if ($scope->province_id) {
                return $scope->province?->name;
            }

            return $scope->region?->short_name ?: $scope->region?->name;
        })->filter()->unique();

        return $labels->isEmpty() ? 'Network scope' : $labels->implode(' · ');
    }

    /**
     * Lightweight, serializable auth context shared with Inertia.
     */
    public function contextFor(User $user): array
    {
        if (isset($this->contextCache[$user->id])) {
            return $this->contextCache[$user->id];
        }

        $this->loadContext($user);

        $hospitals = $this->managementHospitals($user)->map(fn (Hospital $hospital) => [
            'id' => $hospital->id,
            'name' => $hospital->name,
            'short_name' => $hospital->short_name,
            'code' => $hospital->hospital_code,
            'ownership_type' => $hospital->ownership_type,
            'status' => $hospital->status,
            'location' => $hospital->location_label,
            'province' => $hospital->province?->name,
            'region' => $hospital->region?->short_name,
        ])->values()->all();

        $permissions = $user->roles->flatMap(fn ($role) => $role->permissions->pluck('name'))
            ->merge($user->memberships->where('status', 'active')
                ->flatMap(fn ($m) => $m->role?->permissions->pluck('name') ?? collect()))
            ->unique()
            ->values()
            ->all();

        return $this->contextCache[$user->id] = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'is_network_user' => $this->isNetworkUser($user),
            'is_super_admin' => $this->isSuperAdmin($user),
            'roles' => $this->roleNames($user),
            'role_slugs' => $this->roleSlugs($user),
            'permissions' => $permissions,
            'scope_label' => $this->scopeLabel($user),
            'primary_hospital_id' => $this->primaryMembership($user)?->hospital_id,
            'has_hospital_membership' => $this->activeMemberships($user)->isNotEmpty(),
            'hospitals' => $hospitals,
        ];
    }
}
