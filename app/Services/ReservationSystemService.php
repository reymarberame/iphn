<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\BedReservation;
use App\Models\BedStatusHistory;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;

class ReservationSystemService
{
    /**
     * Release an active reservation without a user permission check.
     * Used only for terminal transitions (cancellation, expiry) where the
     * authorized referral action has already been verified.
     */
    public function releaseForReferral(Referral $referral, string $reason): void
    {
        DB::transaction(function () use ($referral, $reason) {
            $reservation = BedReservation::query()
                ->where('referral_id', $referral->id)
                ->where('status', BedReservation::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $reservation) {
                return;
            }

            $reservation->forceFill([
                'status' => BedReservation::STATUS_CANCELLED,
                'released_at' => now(),
                'notes' => $reason,
            ])->save();

            $bed = Bed::query()->lockForUpdate()->find($reservation->bed_id);

            if ($bed && $bed->status === Bed::STATUS_RESERVED) {
                $bed->forceFill(['status' => Bed::STATUS_AVAILABLE, 'updated_at' => now()])->save();

                BedStatusHistory::create([
                    'bed_id' => $bed->id,
                    'hospital_id' => $bed->hospital_id,
                    'old_status' => Bed::STATUS_RESERVED,
                    'new_status' => Bed::STATUS_AVAILABLE,
                    'reason' => $reason,
                    'changed_by' => null,
                    'changed_at' => now(),
                ]);
            }
        });
    }
}
