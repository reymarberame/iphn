<?php

namespace App\Policies;

use App\Models\User;
use App\Services\HospitalAccessService;

class ReportPolicy
{
    public function __construct(private readonly HospitalAccessService $access) {}

    public function view(User $user): bool
    {
        return $this->access->hasPermission($user, 'reports.view')
            || $this->access->hasPermission($user, 'reports.national');
    }

    public function national(User $user): bool
    {
        return $this->access->hasPermission($user, 'reports.national');
    }
}
