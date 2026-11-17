<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Ward extends Model
{
    protected $fillable = ['hospital_id', 'name', 'ward_type', 'floor', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function beds(): HasManyThrough
    {
        return $this->hasManyThrough(Bed::class, Room::class);
    }
}
