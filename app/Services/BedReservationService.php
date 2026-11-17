<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\BedReservation;
use App\Models\BedStatusHistory;
use App\Models\Referral;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BedReservationService
{
    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function reserve(Referral $referral, Bed $bed, User $user): BedReservation
    {
        if (! $this->access->canRespondToReferral($user, $referral)
            || ! $this->access->hasHospitalPermission($user, $referral->receiving_hospital_id, 'beds.reserve')) {
            abort(403, 'Only authorized staff at the receiving hospital may reserve a bed.');
        }

        $reservation = DB::transaction(function () use ($referral, $bed, $user) {
            $lockedReferral = Referral::query()->lockForUpdate()->findOrFail($referral->id);

            if ($lockedReferral->status !== Referral::STATUS_ACCEPTED) {
                throw ValidationException::withMessages([
                    'referral' => 'A bed can only be reserved after the receiving hospital accepts the referral.',
                ]);
            }

            if ($lockedReferral->activeReservation()->exists()) {
                throw ValidationException::withMessages(['bed' => 'This referral already has an active bed reservation.']);
            }

            $lockedBed = Bed::query()->with(['category', 'room.ward'])->lockForUpdate()->findOrFail($bed->id);

            if ($lockedBed->hospital_id !== $lockedReferral->receiving_hospital_id) {
                throw ValidationException::withMessages(['bed' => 'The selected bed does not belong to the receiving hospital.']);
            }

            if (! $lockedBed->active) {
                throw ValidationException::withMessages(['bed' => 'The selected bed is inactive.']);
            }

            if ($lockedBed->status === Bed::STATUS_OUT_OF_SERVICE) {
                throw ValidationException::withMessages(['bed' => 'An out-of-service bed cannot be reserved.']);
            }

            if ($lockedBed->status !== Bed::STATUS_AVAILABLE) {
                throw ValidationException::withMessages([
                    'bed' => 'This bed is currently '.$lockedBed->statusLabel().' and cannot be reserved.',
                ]);
            }

            $alreadyHeld = BedReservation::query()
                ->where('bed_id', $lockedBed->id)
                ->where('status', BedReservation::STATUS_ACTIVE)
                ->lockForUpdate()
                ->exists();

            if ($alreadyHeld) {
                throw ValidationException::withMessages(['bed' => 'This bed was just reserved by another user. Choose another bed.']);
            }

            $ttl = (int) SystemSetting::get('reservation_ttl_minutes', 120);

            $reservation = BedReservation::create([
                'referral_id' => $lockedReferral->id,
                'bed_id' => $lockedBed->id,
                'hospital_id' => $lockedBed->hospital_id,
                'status' => BedReservation::STATUS_ACTIVE,
                'reserved_by' => $user->id,
                'reserved_at' => now(),
                'expires_at' => now()->addMinutes($ttl),
                'notes' => 'Held for referral '.$lockedReferral->referral_number,
            ]);

            $oldStatus = $lockedBed->status;
            $lockedBed->forceFill([
                'status' => Bed::STATUS_RESERVED,
                'updated_by' => $user->id,
                'updated_at' => now(),
            ])->save();

            BedStatusHistory::create([
                'bed_id' => $lockedBed->id,
                'hospital_id' => $lockedBed->hospital_id,
                'old_status' => $oldStatus,
                'new_status' => Bed::STATUS_RESERVED,
                'reason' => 'Reserved for referral '.$lockedReferral->referral_number,
                'changed_by' => $user->id,
                'changed_at' => now(),
            ]);

            $this->audit->record('bed.reserved', $lockedReferral, [
                'referral_number' => $lockedReferral->referral_number,
                'bed_code' => $lockedBed->bed_code,
                'bed_id' => $lockedBed->id,
                'expires_at' => $reservation->expires_at?->toIso8601String(),
            ], $lockedBed->hospital_id, $user);

            return $reservation->fresh(['bed.category', 'bed.room.ward', 'referral']);
        });

        $this->notifications->bedReserved($reservation->referral, $reservation->bed, $user);

        return $reservation;
    }

    public function release(BedReservation $reservation, User $user, string $status = BedReservation::STATUS_RELEASED, ?string $reason = null): BedReservation
    {
        if (! in_array($status, [BedReservation::STATUS_RELEASED, BedReservation::STATUS_CANCELLED, BedReservation::STATUS_EXPIRED], true)) {
            throw ValidationException::withMessages(['status' => 'Invalid reservation release status.']);
        }

        if (! $this->access->hasActiveMembership($user, $reservation->hospital_id)
            || (! $this->access->hasHospitalPermission($user, $reservation->hospital_id, 'referrals.respond')
                && ! $this->access->hasHospitalPermission($user, $reservation->hospital_id, 'beds.reserve'))) {
            abort(403, 'You are not allowed to release this reservation.');
        }

        return DB::transaction(function () use ($reservation, $status, $reason, $user) {
            $locked = BedReservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ($locked->status !== BedReservation::STATUS_ACTIVE) {
                return $locked;
            }

            $locked->forceFill([
                'status' => $status,
                'released_at' => now(),
                'notes' => $reason ?: $locked->notes,
            ])->save();

            $bed = Bed::query()->lockForUpdate()->find($locked->bed_id);

            if ($bed && $bed->status === Bed::STATUS_RESERVED) {
                $bed->forceFill([
                    'status' => Bed::STATUS_AVAILABLE,
                    'updated_by' => $user->id,
                    'updated_at' => now(),
                ])->save();

                BedStatusHistory::create([
                    'bed_id' => $bed->id,
                    'hospital_id' => $bed->hospital_id,
                    'old_status' => Bed::STATUS_RESERVED,
                    'new_status' => Bed::STATUS_AVAILABLE,
                    'reason' => $reason ?: 'Bed reservation released',
                    'changed_by' => $user->id,
                    'changed_at' => now(),
                ]);
            }

            $this->audit->record('bed.reservation.released', $locked, [
                'reservation_id' => $locked->id,
                'status' => $status,
                'reason' => $reason,
            ], $locked->hospital_id, $user);

            return $locked->fresh(['bed']);
        });
    }

    public function releaseForReferral(Referral $referral, User $user, ?string $reason = null): void
    {
        $reservation = $referral->activeReservation()->first();

        if ($reservation) {
            $this->release($reservation, $user, BedReservation::STATUS_CANCELLED, $reason);
        }
    }

    public function consumeOnArrival(Referral $referral, User $user): void
    {
        DB::transaction(function () use ($referral, $user) {
            $reservation = BedReservation::query()
                ->where('referral_id', $referral->id)
                ->where('status', BedReservation::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $reservation) {
                return;
            }

            $reservation->forceFill([
                'status' => BedReservation::STATUS_CONSUMED,
                'notes' => 'Patient arrival confirmed',
            ])->save();

            $bed = Bed::query()->lockForUpdate()->find($reservation->bed_id);

            if ($bed && $bed->status !== Bed::STATUS_OCCUPIED) {
                $oldStatus = $bed->status;
                $bed->forceFill([
                    'status' => Bed::STATUS_OCCUPIED,
                    'updated_by' => $user->id,
                    'updated_at' => now(),
                ])->save();

                BedStatusHistory::create([
                    'bed_id' => $bed->id,
                    'hospital_id' => $bed->hospital_id,
                    'old_status' => $oldStatus,
                    'new_status' => Bed::STATUS_OCCUPIED,
                    'reason' => 'Patient arrival confirmed for referral '.$referral->referral_number,
                    'changed_by' => $user->id,
                    'changed_at' => now(),
                ]);
            }

            $this->audit->record('bed.reservation.consumed', $reservation, [
                'reservation_id' => $reservation->id,
                'bed_id' => $reservation->bed_id,
            ], $reservation->hospital_id, $user);
        });
    }

    public function expireOverdue(): int
    {
        $overdue = BedReservation::query()
            ->where('status', BedReservation::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;

        foreach ($overdue as $reservation) {
            DB::transaction(function () use ($reservation) {
                $locked = BedReservation::query()->lockForUpdate()->find($reservation->id);

                if (! $locked || $locked->status !== BedReservation::STATUS_ACTIVE) {
                    return;
                }

                $locked->forceFill([
                    'status' => BedReservation::STATUS_EXPIRED,
                    'released_at' => now(),
                    'notes' => 'Reservation expired automatically.',
                ])->save();

                $bed = Bed::query()->lockForUpdate()->find($locked->bed_id);

                if ($bed && $bed->status === Bed::STATUS_RESERVED) {
                    $bed->forceFill(['status' => Bed::STATUS_AVAILABLE, 'updated_at' => now()])->save();

                    BedStatusHistory::create([
                        'bed_id' => $bed->id,
                        'hospital_id' => $bed->hospital_id,
                        'old_status' => Bed::STATUS_RESERVED,
                        'new_status' => Bed::STATUS_AVAILABLE,
                        'reason' => 'Reservation expired',
                        'changed_by' => null,
                        'changed_at' => now(),
                    ]);
                }
            });
            $count++;
        }

        return $count;
    }
}
