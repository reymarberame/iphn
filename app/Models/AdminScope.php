<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminScope extends Model
{
    protected $fillable = ['user_id', 'region_id', 'province_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
