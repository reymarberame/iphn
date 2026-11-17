<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BedReservation extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_RELEASED = 'released';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CONSUMED = 'consumed';

    protected $fillable = [
        'referral_id', 'bed_id', 'hospital_id', 'status', 'reserved_by',
        'reserved_at', 'expires_at', 'released_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function reserver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }
}
