<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferRecord extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_READY = 'ready';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_ARRIVED = 'arrived';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_SCHEDULED => 'Scheduled',
        self::STATUS_READY => 'Ready for departure',
        self::STATUS_IN_TRANSIT => 'In transit',
        self::STATUS_ARRIVED => 'Arrived',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const TRANSPORT_LABELS = [
        'ambulance' => 'Ambulance',
        'private_vehicle' => 'Private vehicle',
        'other' => 'Other',
    ];

    protected $fillable = [
        'referral_id', 'status', 'transport_type', 'vehicle_reference', 'escort_contact',
        'departure_at', 'estimated_arrival_at', 'arrival_at', 'coordinator_id', 'created_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'departure_at' => 'datetime',
            'estimated_arrival_at' => 'datetime',
            'arrival_at' => 'datetime',
        ];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }
}
