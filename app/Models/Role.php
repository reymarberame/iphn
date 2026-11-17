<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'scope_type', 'description'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(HospitalUserMembership::class);
    }

    public function isNetworkScoped(): bool
    {
        return in_array($this->scope_type, ['network', 'region', 'province'], true);
    }
}
