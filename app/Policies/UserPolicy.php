<?php

namespace App\Policies;

use App\Models\User;
use App\Services\HospitalAccessService;

class UserPolicy
{
    public function __construct(private readonly HospitalAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->hasPermission($user, 'users.manage');
    }

    public function create(User $user): bool
    {
        return $this->access->hasPermission($user, 'users.manage');
    }

    public function update(User $actor, User $target): bool
    {
        if (! $this->access->hasPermission($actor, 'users.manage') || $target->trashed()) {
            return false;
        }

        if ($actor->id === $target->id) {
            return true;
        }

        if ($this->access->isSuperAdmin($actor)) {
            return true;
        }

        if ($this->access->isNetworkUser($actor)) {
            return ! $this->access->isSuperAdmin($target);
        }

        $targetHospitalIds = $target->memberships()->pluck('hospital_id')->unique();
        $manageable = collect($this->access->managementHospitalIds($actor));

        return $targetHospitalIds->isNotEmpty()
            && $targetHospitalIds->diff($manageable)->isEmpty()
            && $manageable->contains(fn (int $hospitalId) => $this->access->hasHospitalPermission($actor, $hospitalId, 'users.manage'));
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->id !== $target->id && $this->update($actor, $target);
    }
}
