<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\NetworkActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function __construct(private readonly HospitalAccessService $access) {}

    public function referralEvent(Referral $referral, string $title, string $message, ?User $actor = null): void
    {
        $users = $this->access->usersWithHospitalPermission($referral->receiving_hospital_id, 'referrals.respond')
            ->merge($this->access->usersWithHospitalPermission($referral->referring_hospital_id, 'referrals.view'))
            ->unique('id')
            ->when($actor, fn (Collection $users) => $users->reject(fn (User $user) => $user->id === $actor->id))
            ->values();

        $this->send($users, new NetworkActivity(
            title: $title,
            message: $message,
            type: 'referral',
            url: '/referrals/'.$referral->id,
            context: [
                'referral_id' => $referral->id,
                'referral_number' => $referral->referral_number,
                'status' => $referral->status,
                'urgency' => $referral->urgency,
            ],
        ));
    }

    public function receivingHospitalEvent(Referral $referral, string $title, string $message, ?User $actor = null): void
    {
        $users = $this->access->usersWithHospitalPermission($referral->receiving_hospital_id, 'referrals.respond')
            ->when($actor, fn (Collection $users) => $users->reject(fn (User $user) => $user->id === $actor->id));

        $this->send($users->values(), new NetworkActivity(
            title: $title,
            message: $message,
            type: 'referral',
            url: '/referrals/'.$referral->id,
            context: ['referral_id' => $referral->id, 'status' => $referral->status],
        ));
    }

    public function bedReserved(Referral $referral, Bed $bed, ?User $actor = null): void
    {
        $users = $this->access->usersWithHospitalPermission($referral->receiving_hospital_id, 'referrals.respond')
            ->merge($this->access->usersWithHospitalPermission($referral->referring_hospital_id, 'referrals.view'))
            ->when($actor, fn (Collection $users) => $users->reject(fn (User $user) => $user->id === $actor->id))
            ->unique('id')
            ->values();

        $this->send($users, new NetworkActivity(
            title: 'Bed reserved for '.$referral->referral_number,
            message: $bed->bed_code.' is now held for the accepted referral at '.$referral->receivingHospital->name.'.',
            type: 'reservation',
            url: '/referrals/'.$referral->id,
            context: ['referral_id' => $referral->id, 'bed_id' => $bed->id],
        ));
    }

    public function staleAvailability(Hospital $hospital, string $lastUpdate): void
    {
        $users = $this->access->usersWithHospitalPermission($hospital->id, 'beds.update');

        $this->send($users, new NetworkActivity(
            title: 'Availability report is stale',
            message: $hospital->name.' has not confirmed bed availability since '.$lastUpdate.'. Please review the bed board.',
            type: 'stale',
            url: '/beds',
            context: ['hospital_id' => $hospital->id, 'severity' => 'warning'],
        ));
    }

    private function send(Collection $users, NetworkActivity $notification): void
    {
        if ($users->isNotEmpty()) {
            Notification::send($users, $notification);
        }
    }
}
