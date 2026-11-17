<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $fillable = ['code', 'name', 'short_name'];

    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }

    public function hospitals(): HasMany
    {
        return $this->hasMany(Hospital::class);
    }
}
