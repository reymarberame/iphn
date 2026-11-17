<?php

namespace App\Services;

use App\Models\Hospital;
use App\Models\HospitalUserMembership;
use App\Models\Referral;
use App\Models\ReferralStatusHistory;
use App\Models\Service;
use App\Models\TransferRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReferralService
{
    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly ReservationSystemService $reservations,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function create(User $user, array $data): Referral
    {
        $referring = Hospital::query()->findOrFail($data['referring_hospital_id']);
        $receiving = Hospital::query()->findOrFail($data['receiving_hospital_id']);

        if (! $this->access->canCreateReferralFrom($user, $referring->id)) {
            abort(403, 'You may only create referrals for a hospital where you hold an active referral role.');
        }

        if ($referring->id === $receiving->id) {
            throw ValidationException::withMessages(['receiving_hospital_id' => 'The receiving hospital must be different from the referring hospital.']);
        }

        if (! in_array($receiving->status, [Hospital::STATUS_OPERATIONAL, Hospital::STATUS_LIMITED], true)) {
            throw ValidationException::withMessages(['receiving_hospital_id' => 'The selected hospital is not accepting referrals.']);
        }

        if (! empty($data['requested_service_id'])) {
            $service = Service::query()->find($data['requested_service_id']);
            $offered = $service && $receiving->services()->whereKey($service->id)->exists();

            if (! $offered) {
                throw ValidationException::withMessages(['requested_service_id' => 'The requested service is not listed by the receiving hospital.']);
            }
        }

        $referral = DB::transaction(function () use ($data, $user, $referring, $receiving) {
            $referral = Referral::create([
                'referral_number' => $this->uniqueReferralNumber(),
                'referring_hospital_id' => $referring->id,
                'receiving_hospital_id' => $receiving->id,
                'patient_reference' => $data['patient_reference'],
                'patient_name' => $data['patient_name'],
                'patient_age' => $data['patient_age'] ?? null,
                'patient_sex' => $data['patient_sex'] ?? null,
                'urgency' => $data['urgency'],
                'requested_service_id' => $data['requested_service_id'] ?? null,
                'referral_reason' => $data['referral_reason'],
                'contact_name' => $data['contact_name'] ?? $user->name,
                'contact_phone' => $data['contact_phone'] ?? $user->phone,
                'status' => Referral::STATUS_DRAFT,
                'created_by' => $user->id,
            ]);

            ReferralStatusHistory::create([
                'referral_id' => $referral->id,
                'old_status' => null,
                'new_status' => Referral::STATUS_DRAFT,
                'reason' => 'Referral created',
                'changed_by' => $user->id,
                'changed_at' => now(),
            ]);

            $this->audit->record('referral.created', $referral, [
                'referral_number' => $referral->referral_number,
                'referring_hospital_id' => $referring->id,
                'receiving_hospital_id' => $receiving->id,
                'urgency' => $referral->urgency,
            ], $referring->id, $user);

            return $referral;
        });

        if (! empty($data['submit'])) {
            return $this->submit($referral, $user);
        }

        return $referral->fresh(['referringHospital', 'receivingHospital', 'requestedService', 'creator']);
    }

    public function submit(Referral $referral, User $user): Referral
    {
        $this->assertReferringCoordinator($referral, $user);

        $updated = $this->transition($referral, Referral::STATUS_SUBMITTED, 'Referral submitted to receiving hospital', $user, [
            'submitted_at' => now(),
        ]);

        $this->notifications->receivingHospitalEvent(
            $updated,
            'New '.$updated->urgencyLabel().' referral',
            $updated->referringHospital->name.' submitted '.$updated->referral_number.' to your hospital.',
            $user
        );

        return $updated;
    }

    public function startReview(Referral $referral, User $user): Referral
    {
        $this->assertReceivingResponder($referral, $user);

        return $this->transition($referral, Referral::STATUS_UNDER_REVIEW, 'Receiving hospital started review', $user);
    }

    public function accept(Referral $referral, User $user, array $data = []): Referral
    {
        $this->assertReceivingResponder($referral, $user);

        $coordinatorId = $data['coordinator_id'] ?? $user->id;
        $this->assertCoordinatorBelongsToReceivingHospital($referral, (int) $coordinatorId);

        $updated = $this->transition($referral, Referral::STATUS_ACCEPTED, $data['response_note'] ?? 'Referral accepted', $user, [
            'accepted_at' => now(),
            'coordinator_id' => $coordinatorId,
            'response_note' => $data['response_note'] ?? null,
        ]);

        $this->notifications->referralEvent(
            $updated,
            'Referral accepted',
            $updated->receivingHospital->name.' accepted '.$updated->referral_number.'. Coordinate the transfer next.',
            $user
        );

        return $updated;
    }

    public function decline(Referral $referral, User $user, string $reason): Referral
    {
        $this->assertReceivingResponder($referral, $user);

        if (blank($reason)) {
            throw ValidationException::withMessages(['decline_reason' => 'A decline reason is required.']);
        }

        $updated = $this->transition($referral, Referral::STATUS_DECLINED, $reason, $user, [
            'decline_reason' => $reason,
            'closed_at' => now(),
        ]);

        $this->notifications->referralEvent(
            $updated,
            'Referral declined',
            $updated->receivingHospital->name.' declined '.$updated->referral_number.'. Reason: '.$reason,
            $user
        );

        return $updated;
    }

    public function requestInformation(Referral $referral, User $user, string $note): Referral
    {
        $this->assertReceivingResponder($referral, $user);

        if (blank($note)) {
            throw ValidationException::withMessages(['note' => 'Describe the additional information required.']);
        }

        $updated = $this->transition($referral, Referral::STATUS_MORE_INFO, $note, $user, [
            'response_note' => $note,
        ]);

        $this->notifications->referralEvent(
            $updated,
            'More information required',
            $updated->receivingHospital->name.' needs more information for '.$updated->referral_number.'.',
            $user
        );

        return $updated;
    }

    public function assignCoordinator(Referral $referral, User $user, int $coordinatorId): Referral
    {
        $this->assertReceivingResponder($referral, $user);
        $this->assertCoordinatorBelongsToReceivingHospital($referral, $coordinatorId);

        $referral->forceFill(['coordinator_id' => $coordinatorId])->save();

        $this->audit->record('referral.coordinator.assigned', $referral, [
            'coordinator_id' => $coordinatorId,
        ], $referral->receiving_hospital_id, $user);

        return $referral->fresh(['coordinator']);
    }

    public function cancel(Referral $referral, User $user, string $reason): Referral
    {
        $isReferring = $this->access->hasActiveMembership($user, $referral->referring_hospital_id)
            && $this->access->hasHospitalPermission($user, $referral->referring_hospital_id, 'referrals.create');
        $isReceiving = $this->access->canRespondToReferral($user, $referral);

        if (! $isReferring && ! $isReceiving) {
            abort(403, 'You are not allowed to cancel this referral.');
        }

        if ($referral->transfer && $referral->transfer->status === TransferRecord::STATUS_IN_TRANSIT) {
            throw ValidationException::withMessages([
                'referral' => 'A referral already in transit cannot be cancelled. Confirm arrival instead.',
            ]);
        }

        $updated = $this->transition($referral, Referral::STATUS_CANCELLED, $reason, $user, [
            'closed_at' => now(),
        ]);

        $this->reservations->releaseForReferral($updated, 'Referral cancelled: '.$reason);
        $updated->transfer?->forceFill(['status' => TransferRecord::STATUS_CANCELLED])->save();

        $this->notifications->referralEvent(
            $updated,
            'Referral cancelled',
            $updated->referral_number.' was cancelled by '.($isReferring ? $updated->referringHospital->name : $updated->receivingHospital->name).'.',
            $user
        );

        return $updated->fresh(['transfer', 'activeReservation']);
    }

    public function uploadDocument(Referral $referral, User $user, \Illuminate\Http\UploadedFile $file): \App\Models\ReferralDocument
    {
        if (! $this->access->canViewReferral($user, $referral)) {
            abort(403);
        }

        $path = $file->storeAs(
            'referral-documents/'.$referral->public_id,
            Str::uuid().'.'.$file->getClientOriginalExtension(),
            'local'
        );

        return $referral->documents()->create([
            'storage_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $user->id,
        ]);
    }

    private function transition(Referral $referral, string $to, string $reason, User $user, array $extra = []): Referral
    {
        $updated = DB::transaction(function () use ($referral, $to, $reason, $user, $extra) {
            $locked = Referral::query()->lockForUpdate()->findOrFail($referral->id);

            if (! $locked->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => 'This referral cannot move from '.$locked->statusLabel().' to '.Referral::STATUS_LABELS[$to].'.',
                ]);
            }

            $oldStatus = $locked->status;
            $locked->forceFill([...$extra, 'status' => $to])->save();

            ReferralStatusHistory::create([
                'referral_id' => $locked->id,
                'old_status' => $oldStatus,
                'new_status' => $to,
                'reason' => $reason,
                'changed_by' => $user->id,
                'changed_at' => now(),
            ]);

            $this->audit->record('referral.status.updated', $locked, [
                'referral_number' => $locked->referral_number,
                'old_status' => $oldStatus,
                'new_status' => $to,
                'reason' => $reason,
            ], $locked->referring_hospital_id, $user);

            return $locked;
        });

        return $updated->fresh(['referringHospital', 'receivingHospital', 'requestedService', 'coordinator', 'transfer', 'activeReservation.bed.category']);
    }

    private function assertReferringCoordinator(Referral $referral, User $user): void
    {
        if (! $this->access->hasActiveMembership($user, $referral->referring_hospital_id)
            || ! $this->access->hasHospitalPermission($user, $referral->referring_hospital_id, 'referrals.create')) {
            abort(403, 'Only the referring hospital can perform this action.');
        }
    }

    private function assertReceivingResponder(Referral $referral, User $user): void
    {
        if (! $this->access->canRespondToReferral($user, $referral)) {
            abort(403, 'Only authorized receiving-hospital staff can respond to this referral.');
        }
    }

    private function assertCoordinatorBelongsToReceivingHospital(Referral $referral, int $coordinatorId): void
    {
        $valid = HospitalUserMembership::query()
            ->where('user_id', $coordinatorId)
            ->where('hospital_id', $referral->receiving_hospital_id)
            ->where('status', 'active')
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages(['coordinator_id' => 'The coordinator must be an active member of the receiving hospital.']);
        }
    }

    private function uniqueReferralNumber(): string
    {
        do {
            $number = 'REF-'.now()->format('Y').'-'.strtoupper(Str::random(6));
        } while (Referral::withTrashed()->where('referral_number', $number)->exists());

        return $number;
    }
}
