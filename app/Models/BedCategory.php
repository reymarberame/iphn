<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BedCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_er', 'sort_order', 'active'];

    protected function casts(): array
    {
        return [
            'is_er' => 'boolean',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }
}
