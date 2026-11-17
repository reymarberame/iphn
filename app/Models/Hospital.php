<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hospital extends Model
{
    use SoftDeletes;

    public const OWNERSHIP_GOVERNMENT = 'government';
    public const OWNERSHIP_PRIVATE = 'private';

    public const STATUS_OPERATIONAL = 'operational';
    public const STATUS_LIMITED = 'limited';
    public const STATUS_INACTIVE = 'inactive';

    public const OWNERSHIP_LABELS = [
        self::OWNERSHIP_GOVERNMENT => 'Government',
        self::OWNERSHIP_PRIVATE => 'Private',
    ];

    public const STATUS_LABELS = [
        self::STATUS_OPERATIONAL => 'Operational',
        self::STATUS_LIMITED => 'Limited services',
        self::STATUS_INACTIVE => 'Inactive',
    ];

    protected $fillable = [
        'municipality_id', 'hospital_code', 'name', 'short_name', 'facility_type',
        'ownership_type', 'address', 'phone', 'email', 'latitude', 'longitude',
        'bed_capacity', 'status', 'notes', 'profile_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'bed_capacity' => 'integer',
            'profile_updated_at' => 'datetime',
        ];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function getProvinceAttribute(): ?Province
    {
        return $this->municipality?->province;
    }

    public function getRegionAttribute(): ?Region
    {
        return $this->province?->region;
    }

    public function getLocationLabelAttribute(): string
    {
        return collect([$this->municipality?->name, $this->province?->name])
            ->filter()
            ->unique()
            ->implode(', ');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'hospital_services')
            ->withPivot('availability_notes')
            ->withTimestamps();
    }

    public function wards(): HasMany
    {
        return $this->hasMany(Ward::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(HospitalUserMembership::class);
    }

    public function referralsReceived(): HasMany
    {
        return $this->hasMany(Referral::class, 'receiving_hospital_id');
    }

    public function referralsSent(): HasMany
    {
        return $this->hasMany(Referral::class, 'referring_hospital_id');
    }

    public function availabilitySnapshots(): HasMany
    {
        return $this->hasMany(BedAvailabilitySnapshot::class);
    }

    public function scopeOperational(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPERATIONAL, self::STATUS_LIMITED]);
    }

    public function ownershipLabel(): string
    {
        return self::OWNERSHIP_LABELS[$this->ownership_type] ?? ucfirst($this->ownership_type);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }
}
