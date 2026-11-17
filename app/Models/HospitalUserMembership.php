<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalUserMembership extends Model
{
    protected $fillable = ['user_id', 'hospital_id', 'role_id', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
