<?php

namespace App\Policies;

use App\Models\User;
use App\Services\HospitalAccessService;

class WardPolicy
{
    public function __construct(private readonly HospitalAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->hasPermission($user, 'beds.view');
    }

    public function create(User $user, int $hospitalId): bool
    {
        return $this->access->hasHospitalPermission($user, $hospitalId, 'beds.manage');
    }
}
