<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function hospitals(): BelongsToMany
    {
        return $this->belongsToMany(Hospital::class, 'hospital_services')
            ->withPivot('availability_notes')
            ->withTimestamps();
    }
}
