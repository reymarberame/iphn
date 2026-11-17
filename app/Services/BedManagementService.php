<?php

namespace App\Services;

use App\Exceptions\StaleBedUpdateException;
use App\Models\Bed;
use App\Models\BedStatusHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BedManagementService
{
    public function __construct(
        private readonly HospitalAccessService $access,
        private readonly AuditLogger $audit,
    ) {}

    public function updateStatus(
        Bed $bed,
        User $user,
        string $status,
        ?string $reason = null,
        ?Carbon $expectedUpdatedAt = null,
        ?int $hospitalId = null,
    ): Bed {
        if (! in_array($status, Bed::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'The selected bed status is invalid.']);
        }

        $hospitalId = $hospitalId ?? $bed->hospital_id;

        if (! $this->access->hasHospitalPermission($user, $hospitalId, 'beds.update')) {
            abort(403, 'You are not allowed to update beds for this hospital.');
        }

        return DB::transaction(function () use ($bed, $user, $status, $reason, $expectedUpdatedAt, $hospitalId) {
            $locked = Bed::query()->with(['room.ward', 'category'])->lockForUpdate()->findOrFail($bed->id);

            if (! $locked->active) {
                throw ValidationException::withMessages(['bed' => 'This bed is inactive and cannot be updated.']);
            }

            if (! $this->access->hasHospitalPermission($user, $locked->hospital_id, 'beds.update')) {
                abort(403, 'You are not allowed to update beds for this hospital.');
            }

            if ($expectedUpdatedAt && $locked->updated_at?->utc()->format('Y-m-d H:i:s') !== $expectedUpdatedAt->utc()->format('Y-m-d H:i:s')) {
                throw new StaleBedUpdateException($locked->id);
            }

            if ($locked->activeReservation()->exists()) {
                if ($status !== Bed::STATUS_RESERVED) {
                    throw ValidationException::withMessages([
                        'status' => 'This bed is held by an active referral reservation. Release the reservation before changing its status.',
                    ]);
                }

                return $locked;
            }

            if ($status === Bed::STATUS_RESERVED) {
                throw ValidationException::withMessages([
                    'status' => 'Reserved status is set only by accepting a referral and allocating a bed. Use the referral workflow to reserve this bed.',
                ]);
            }

            if ($status === Bed::STATUS_OUT_OF_SERVICE && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'A reason is required when taking a bed out of service.']);
            }

            if ($locked->status === $status) {
                return $locked;
            }

            $oldStatus = $locked->status;
            $locked->forceFill([
                'status' => $status,
                'updated_by' => $user->id,
                'updated_at' => now(),
            ])->save();

            BedStatusHistory::create([
                'bed_id' => $locked->id,
                'hospital_id' => $locked->hospital_id,
                'old_status' => $oldStatus,
                'new_status' => $status,
                'reason' => $reason,
                'changed_by' => $user->id,
                'changed_at' => now(),
            ]);

            $this->audit->record(
                action: 'bed.status.updated',
                subject: $locked,
                changes: [
                    'bed_code' => $locked->bed_code,
                    'old_status' => $oldStatus,
                    'new_status' => $status,
                    'reason' => $reason,
                ],
                hospitalId: $locked->hospital_id,
                user: $user,
            );

            return $locked->fresh(['room.ward', 'category', 'updater']);
        });
    }

    public function bulkUpdate(
        User $user,
        int $hospitalId,
        array $bedIds,
        string $status,
        ?string $reason = null,
    ): int {
        if (! in_array($status, Bed::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'The selected bed status is invalid.']);
        }

        if (! $this->access->hasHospitalPermission($user, $hospitalId, 'beds.update')) {
            abort(403, 'You are not allowed to update beds for this hospital.');
        }

        $bedIds = array_values(array_unique(array_map('intval', $bedIds)));

        if ($bedIds === []) {
            throw ValidationException::withMessages(['bed_ids' => 'Select at least one bed.']);
        }

        if ($status === Bed::STATUS_RESERVED || ($status === Bed::STATUS_OUT_OF_SERVICE && blank($reason))) {
            throw ValidationException::withMessages([
                'status' => $status === Bed::STATUS_RESERVED
                    ? 'Reserved status must be set through the referral workflow.'
                    : 'A reason is required when taking beds out of service.',
            ]);
        }

        return DB::transaction(function () use ($user, $hospitalId, $bedIds, $status, $reason) {
            $beds = Bed::query()
                ->whereIn('id', $bedIds)
                ->where('hospital_id', $hospitalId)
                ->where('active', true)
                ->lockForUpdate()
                ->get();

            if ($beds->count() !== count($bedIds)) {
                throw ValidationException::withMessages(['bed_ids' => 'One or more selected beds are not part of this hospital inventory.']);
            }

            $updated = 0;

            foreach ($beds as $bed) {
                if ($bed->activeReservation()->exists()) {
                    continue;
                }

                if ($bed->status === $status) {
                    continue;
                }

                $oldStatus = $bed->status;
                $bed->forceFill(['status' => $status, 'updated_by' => $user->id, 'updated_at' => now()])->save();

                BedStatusHistory::create([
                    'bed_id' => $bed->id,
                    'hospital_id' => $bed->hospital_id,
                    'old_status' => $oldStatus,
                    'new_status' => $status,
                    'reason' => $reason ?: 'Batch update',
                    'changed_by' => $user->id,
                    'changed_at' => now(),
                ]);

                $this->audit->record('bed.status.bulk_updated', $bed, [
                    'bed_code' => $bed->bed_code,
                    'old_status' => $oldStatus,
                    'new_status' => $status,
                    'reason' => $reason,
                ], $bed->hospital_id, $user);

                $updated++;
            }

            return $updated;
        });
    }
}
