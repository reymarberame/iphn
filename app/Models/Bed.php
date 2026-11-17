<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bed extends Model
{
    use SoftDeletes;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_OCCUPIED = 'occupied';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_CLEANING = 'cleaning';
    public const STATUS_OUT_OF_SERVICE = 'out_of_service';

    public const STATUS_LABELS = [
        self::STATUS_AVAILABLE => 'Available',
        self::STATUS_OCCUPIED => 'Occupied',
        self::STATUS_RESERVED => 'Reserved',
        self::STATUS_CLEANING => 'Cleaning',
        self::STATUS_OUT_OF_SERVICE => 'Out of service',
    ];

    public const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_OCCUPIED,
        self::STATUS_RESERVED,
        self::STATUS_CLEANING,
        self::STATUS_OUT_OF_SERVICE,
    ];

    protected $fillable = [
        'hospital_id', 'room_id', 'bed_category_id', 'bed_code', 'status',
        'notes', 'active', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BedCategory::class, 'bed_category_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(BedStatusHistory::class)->latest('changed_at');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(BedReservation::class);
    }

    public function activeReservation(): HasOne
    {
        return $this->hasOne(BedReservation::class)->where('status', 'active');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOperational(Builder $query): Builder
    {
        return $query->where('active', true)->where('status', '!=', self::STATUS_OUT_OF_SERVICE);
    }

    public function isOperational(): bool
    {
        return $this->active && $this->status !== self::STATUS_OUT_OF_SERVICE;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }
}
