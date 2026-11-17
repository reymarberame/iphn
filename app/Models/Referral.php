<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Referral extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_MORE_INFO = 'more_information_required';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_TRANSFER_SCHEDULED = 'transfer_scheduled';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_ARRIVED = 'arrived';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_UNDER_REVIEW => 'Under review',
        self::STATUS_MORE_INFO => 'More information required',
        self::STATUS_ACCEPTED => 'Accepted',
        self::STATUS_DECLINED => 'Declined',
        self::STATUS_TRANSFER_SCHEDULED => 'Transfer scheduled',
        self::STATUS_IN_TRANSIT => 'In transit',
        self::STATUS_ARRIVED => 'Arrived',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_EXPIRED => 'Expired',
    ];

    public const URGENCY_LABELS = [
        'routine' => 'Routine',
        'urgent' => 'Urgent',
        'emergency' => 'Emergency',
    ];

    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SUBMITTED, self::STATUS_CANCELLED],
        self::STATUS_SUBMITTED => [self::STATUS_UNDER_REVIEW, self::STATUS_ACCEPTED, self::STATUS_DECLINED, self::STATUS_MORE_INFO, self::STATUS_CANCELLED, self::STATUS_EXPIRED],
        self::STATUS_UNDER_REVIEW => [self::STATUS_ACCEPTED, self::STATUS_DECLINED, self::STATUS_MORE_INFO, self::STATUS_CANCELLED, self::STATUS_EXPIRED],
        self::STATUS_MORE_INFO => [self::STATUS_SUBMITTED, self::STATUS_UNDER_REVIEW, self::STATUS_ACCEPTED, self::STATUS_DECLINED, self::STATUS_CANCELLED, self::STATUS_EXPIRED],
        self::STATUS_ACCEPTED => [self::STATUS_TRANSFER_SCHEDULED, self::STATUS_CANCELLED],
        self::STATUS_TRANSFER_SCHEDULED => [self::STATUS_IN_TRANSIT, self::STATUS_CANCELLED],
        self::STATUS_IN_TRANSIT => [self::STATUS_ARRIVED, self::STATUS_CANCELLED],
        self::STATUS_ARRIVED => [self::STATUS_COMPLETED],
        self::STATUS_DECLINED => [],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_EXPIRED => [],
    ];

    protected $fillable = [
        'public_id', 'referral_number', 'referring_hospital_id', 'receiving_hospital_id',
        'patient_reference', 'patient_name', 'patient_age', 'patient_sex', 'urgency',
        'requested_service_id', 'referral_reason', 'contact_name', 'contact_phone',
        'status', 'created_by', 'coordinator_id', 'response_note', 'decline_reason',
        'submitted_at', 'accepted_at', 'closed_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'patient_age' => 'integer',
            'submitted_at' => 'datetime',
            'accepted_at' => 'datetime',
            'closed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Referral $referral) {
            $referral->public_id ??= (string) Str::ulid();
            $referral->referral_number ??= 'REF-'.now()->format('Y').'-'.strtoupper(Str::random(8));
        });
    }

    public function referringHospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'referring_hospital_id');
    }

    public function receivingHospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'receiving_hospital_id');
    }

    public function requestedService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'requested_service_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ReferralStatusHistory::class)->latest('changed_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ReferralDocument::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(BedReservation::class);
    }

    public function activeReservation(): HasOne
    {
        return $this->hasOne(BedReservation::class)->where('status', 'active');
    }

    public function transfer(): HasOne
    {
        return $this->hasOne(TransferRecord::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_DECLINED, self::STATUS_COMPLETED,
            self::STATUS_CANCELLED, self::STATUS_EXPIRED,
        ], true);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function urgencyLabel(): string
    {
        return self::URGENCY_LABELS[$this->urgency] ?? ucfirst($this->urgency);
    }
}
