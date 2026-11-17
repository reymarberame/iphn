<?php

namespace App\Policies;

use App\Models\Hospital;
use App\Models\User;
use App\Services\HospitalAccessService;

class HospitalPolicy
{
    public function __construct(private readonly HospitalAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, Hospital $hospital): bool
    {
        return $this->access->canViewHospital($user, $hospital);
    }

    public function create(User $user): bool
    {
        return $this->access->hasPermission($user, 'hospitals.register');
    }

    public function update(User $user, Hospital $hospital): bool
    {
        if ($hospital->trashed()) {
            return false;
        }

        return $this->access->hasHospitalPermission($user, $hospital->id, 'hospitals.manage')
            || ($this->access->hasPermission($user, 'hospitals.manage') && $this->access->canManageHospital($user, $hospital->id));
    }

    public function manageServices(User $user, Hospital $hospital): bool
    {
        return $this->update($user, $hospital);
    }
}
