<?php

namespace App\Policies;

use App\Models\TransferRecord;
use App\Models\User;

class TransferRecordPolicy
{
    public function view(User $user, TransferRecord $transfer): bool
    {
        return app(\App\Services\HospitalAccessService::class)->canViewReferral($user, $transfer->referral);
    }

    public function manage(User $user, TransferRecord $transfer): bool
    {
        $access = app(\App\Services\HospitalAccessService::class);
        $referral = $transfer->referral;

        return $access->canViewReferral($user, $referral);
    }
}
