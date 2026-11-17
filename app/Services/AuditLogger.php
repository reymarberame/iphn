<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function record(
        string $action,
        ?Model $subject = null,
        array $changes = [],
        ?int $hospitalId = null,
        ?User $user = null,
    ): AuditLog {
        $user ??= auth()->user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'hospital_id' => $hospitalId,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $changes,
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
