<?php

namespace App\Policies;

use App\Models\Bed;
use App\Models\User;
use App\Services\HospitalAccessService;

class BedPolicy
{
    public function __construct(private readonly HospitalAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->hasPermission($user, 'beds.view');
    }

    public function view(User $user, Bed $bed): bool
    {
        // Individual bed records are operational data: only staff attached to
        // the hospital (or a network user whose scope covers it) may read them.
        return $this->access->hasPermission($user, 'beds.view')
            && $this->access->hasHospitalPermission($user, $bed->hospital_id, 'beds.view');
    }

    public function create(User $user, int $hospitalId): bool
    {
        return $this->access->hasHospitalPermission($user, $hospitalId, 'beds.manage');
    }

    public function update(User $user, Bed $bed): bool
    {
        return $this->access->hasHospitalPermission($user, $bed->hospital_id, 'beds.manage');
    }

    public function updateStatus(User $user, Bed $bed): bool
    {
        return $this->access->hasHospitalPermission($user, $bed->hospital_id, 'beds.update');
    }

    public function viewHistory(User $user, Bed $bed): bool
    {
        return $this->access->hasHospitalPermission($user, $bed->hospital_id, 'beds.view');
    }
}
