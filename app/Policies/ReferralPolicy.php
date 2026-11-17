<?php

namespace App\Policies;

use App\Models\Referral;
use App\Models\User;
use App\Services\HospitalAccessService;

class ReferralPolicy
{
    public function __construct(private readonly HospitalAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->hasPermission($user, 'referrals.view');
    }

    public function view(User $user, Referral $referral): bool
    {
        return $this->access->canViewReferral($user, $referral);
    }

    public function create(User $user): bool
    {
        return $this->access->hasPermission($user, 'referrals.create');
    }

    public function respond(User $user, Referral $referral): bool
    {
        return $this->access->canRespondToReferral($user, $referral);
    }

    public function transfer(User $user, Referral $referral): bool
    {
        return $this->access->hasActiveMembership($user, $referral->referring_hospital_id)
            && $this->access->hasHospitalPermission($user, $referral->referring_hospital_id, 'transfers.manage');
    }
}
