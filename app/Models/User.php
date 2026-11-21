<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'status',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Store emails in lowercase so lookups remain consistent on PostgreSQL,
     * where string comparisons are case-sensitive (unlike MySQL's default
     * case-insensitive collation).
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Str::lower($value),
        );
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(HospitalUserMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', 'active');
    }

    public function adminScopes(): HasMany
    {
        return $this->hasMany(AdminScope::class);
    }

    public function hospitals(): BelongsToMany
    {
        return $this->belongsToMany(Hospital::class, 'hospital_user_memberships')
            ->withPivot(['role_id', 'status'])
            ->withTimestamps();
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot(['last_read_message_id', 'last_read_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function referralsCreated(): HasMany
    {
        return $this->hasMany(Referral::class, 'created_by');
    }

    public function referralsCoordinated(): HasMany
    {
        return $this->hasMany(Referral::class, 'coordinator_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasRole(string|array $slugs): bool
    {
        $slugs = (array) $slugs;

        return $this->roles->pluck('slug')
            ->merge($this->activeMemberships->loadMissing('role')->pluck('role.slug'))
            ->filter()
            ->intersect($slugs)
            ->isNotEmpty();
    }

    public function roleSlugs(): array
    {
        return $this->roles->pluck('slug')
            ->merge($this->activeMemberships->loadMissing('role')->pluck('role.slug'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function isNetworkUser(): bool
    {
        return $this->roles->contains(fn (Role $role) => in_array($role->scope_type, ['network', 'region', 'province'], true));
    }
}
