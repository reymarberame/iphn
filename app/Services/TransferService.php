<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\ReferralStatusHistory;
use App\Models\TransferRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferService
{
    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly BedReservationService $reservations,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Referral $referral, User $user, array $data): TransferRecord
    {
        $this->assertReferringCoordinator($referral, $user);

        $transfer = DB::transaction(function () use ($referral, $user, $data) {
            $locked = Referral::query()->lockForUpdate()->findOrFail($referral->id);

            if ($locked->status !== Referral::STATUS_ACCEPTED) {
                throw ValidationException::withMessages([
                    'transfer' => 'A transfer can only be scheduled after the referral is accepted.',
                ]);
            }

            if ($locked->transfer()->exists()) {
                throw ValidationException::withMessages(['transfer' => 'A transfer record already exists for this referral.']);
            }

            $transfer = TransferRecord::create([
                'referral_id' => $locked->id,
                'status' => TransferRecord::STATUS_SCHEDULED,
                'transport_type' => $data['transport_type'],
                'vehicle_reference' => $data['vehicle_reference'] ?? null,
                'escort_contact' => $data['escort_contact'] ?? null,
                'departure_at' => ! empty($data['departure_at']) ? Carbon::parse($data['departure_at']) : null,
                'estimated_arrival_at' => ! empty($data['estimated_arrival_at']) ? Carbon::parse($data['estimated_arrival_at']) : null,
                'coordinator_id' => $locked->coordinator_id ?? $user->id,
                'created_by' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->transitionReferral($locked, Referral::STATUS_TRANSFER_SCHEDULED, 'Transfer scheduled', $user);

            $this->audit->record('transfer.created', $transfer, [
                'referral_number' => $locked->referral_number,
                'transport_type' => $transfer->transport_type,
                'estimated_arrival_at' => $transfer->estimated_arrival_at?->toIso8601String(),
            ], $locked->referring_hospital_id, $user);

            return $transfer;
        });

        $referral->refresh()->load(['referringHospital', 'receivingHospital']);
        $this->notifications->referralEvent(
            $referral,
            'Transfer scheduled',
            $referral->referringHospital->name.' scheduled the transfer for '.$referral->referral_number.'.',
            $user
        );

        return $transfer->fresh(['referral', 'coordinator']);
    }

    public function departure(TransferRecord $transfer, User $user, array $data = []): TransferRecord
    {
        $referral = $transfer->referral;
        $this->assertReferringCoordinator($referral, $user);

        $updated = DB::transaction(function () use ($transfer, $user, $referral, $data) {
            $locked = TransferRecord::query()->lockForUpdate()->findOrFail($transfer->id);

            if (! in_array($locked->status, [TransferRecord::STATUS_SCHEDULED, TransferRecord::STATUS_READY], true)) {
                throw ValidationException::withMessages(['transfer' => 'Departure can only be recorded for a scheduled transfer.']);
            }

            $locked->forceFill([
                'status' => TransferRecord::STATUS_IN_TRANSIT,
                'departure_at' => ! empty($data['departure_at']) ? Carbon::parse($data['departure_at']) : now(),
                'vehicle_reference' => $data['vehicle_reference'] ?? $locked->vehicle_reference,
                'escort_contact' => $data['escort_contact'] ?? $locked->escort_contact,
            ])->save();

            $lockedReferral = Referral::query()->lockForUpdate()->findOrFail($referral->id);
            $this->transitionReferral($lockedReferral, Referral::STATUS_IN_TRANSIT, 'Patient departed referring hospital', $user);

            $this->audit->record('transfer.departed', $locked, [
                'referral_number' => $lockedReferral->referral_number,
                'departure_at' => $locked->departure_at?->toIso8601String(),
                'vehicle_reference' => $locked->vehicle_reference,
            ], $referral->referring_hospital_id, $user);

            return $locked;
        });

        $referral->refresh()->load(['referringHospital', 'receivingHospital']);
        $this->notifications->referralEvent(
            $referral,
            'Patient in transit',
            $referral->referral_number.' departed '.$referral->referringHospital->name.'. Confirm arrival at the receiving hospital.',
            $user
        );

        return $updated->fresh(['referral', 'coordinator']);
    }

    public function arrival(TransferRecord $transfer, User $user, array $data = []): TransferRecord
    {
        $referral = $transfer->referral;
        $this->assertReceivingCoordinator($referral, $user);

        $updated = DB::transaction(function () use ($transfer, $user, $referral, $data) {
            $locked = TransferRecord::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($locked->status !== TransferRecord::STATUS_IN_TRANSIT) {
                throw ValidationException::withMessages(['transfer' => 'Record departure before confirming arrival.']);
            }

            $locked->forceFill([
                'status' => TransferRecord::STATUS_ARRIVED,
                'arrival_at' => ! empty($data['arrival_at']) ? Carbon::parse($data['arrival_at']) : now(),
                'notes' => $data['notes'] ?? $locked->notes,
            ])->save();

            $lockedReferral = Referral::query()->lockForUpdate()->findOrFail($referral->id);
            $this->transitionReferral($lockedReferral, Referral::STATUS_ARRIVED, 'Receiving hospital confirmed arrival', $user);

            $this->reservations->consumeOnArrival($lockedReferral, $user);

            $this->audit->record('transfer.arrived', $locked, [
                'referral_number' => $lockedReferral->referral_number,
                'arrival_at' => $locked->arrival_at?->toIso8601String(),
            ], $referral->referring_hospital_id, $user);

            return $locked;
        });

        $referral->refresh()->load(['referringHospital', 'receivingHospital']);
        $this->notifications->referralEvent(
            $referral,
            'Patient arrived',
            $referral->referral_number.' arrived at '.$referral->receivingHospital->name.'.',
            $user
        );

        return $updated->fresh(['referral', 'coordinator']);
    }

    public function complete(TransferRecord $transfer, User $user): TransferRecord
    {
        $referral = $transfer->referral;
        $this->assertReceivingCoordinator($referral, $user);

        $updated = DB::transaction(function () use ($transfer, $user, $referral) {
            $locked = TransferRecord::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($locked->status !== TransferRecord::STATUS_ARRIVED) {
                throw ValidationException::withMessages(['transfer' => 'Only an arrived transfer can be completed.']);
            }

            $locked->forceFill(['status' => TransferRecord::STATUS_COMPLETED])->save();

            $lockedReferral = Referral::query()->lockForUpdate()->findOrFail($referral->id);
            $this->transitionReferral($lockedReferral, Referral::STATUS_COMPLETED, 'Transfer completed', $user);
            $lockedReferral->forceFill(['closed_at' => now()])->save();

            $this->audit->record('transfer.completed', $locked, [
                'referral_number' => $lockedReferral->referral_number,
            ], $referral->referring_hospital_id, $user);

            return $locked;
        });

        $referral->refresh()->load(['referringHospital', 'receivingHospital']);
        $this->notifications->referralEvent(
            $referral,
            'Transfer completed',
            $referral->referral_number.' has been completed. No clinical record is stored in IPHN.',
            $user
        );

        return $updated->fresh(['referral', 'coordinator']);
    }

    private function transitionReferral(Referral $referral, string $to, string $reason, User $user): void
    {
        if ($referral->status === $to) {
            return;
        }

        if (! $referral->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => 'Referral cannot move from '.$referral->statusLabel().' to '.Referral::STATUS_LABELS[$to].'.',
            ]);
        }

        $old = $referral->status;
        $referral->forceFill(['status' => $to])->save();

        ReferralStatusHistory::create([
            'referral_id' => $referral->id,
            'old_status' => $old,
            'new_status' => $to,
            'reason' => $reason,
            'changed_by' => $user->id,
            'changed_at' => now(),
        ]);
    }

    private function assertReferringCoordinator(Referral $referral, User $user): void
    {
        if (! $this->access->hasActiveMembership($user, $referral->referring_hospital_id)
            || ! $this->access->hasHospitalPermission($user, $referral->referring_hospital_id, 'transfers.manage')) {
            abort(403, 'Only authorized staff at the referring hospital can coordinate this transfer.');
        }
    }

    private function assertReceivingCoordinator(Referral $referral, User $user): void
    {
        if (! $this->access->hasActiveMembership($user, $referral->receiving_hospital_id)
            || (! $this->access->hasHospitalPermission($user, $referral->receiving_hospital_id, 'transfers.manage')
                && ! $this->access->hasHospitalPermission($user, $referral->receiving_hospital_id, 'referrals.respond'))) {
            abort(403, 'Only authorized staff at the receiving hospital can confirm this transfer.');
        }
    }
}
