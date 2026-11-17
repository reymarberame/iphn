<?php

namespace App\Policies;

use App\Models\User;
use App\Services\HospitalAccessService;

class RoomPolicy
{
    public function __construct(private readonly HospitalAccessService $access) {}

    public function create(User $user, int $hospitalId): bool
    {
        return $this->access->hasHospitalPermission($user, $hospitalId, 'beds.manage');
    }
}
