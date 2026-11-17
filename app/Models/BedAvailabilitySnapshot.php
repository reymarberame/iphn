<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BedAvailabilitySnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['hospital_id', 'category_counts', 'total_beds', 'available_beds', 'captured_at'];

    protected function casts(): array
    {
        return [
            'category_counts' => 'array',
            'captured_at' => 'datetime',
            'total_beds' => 'integer',
            'available_beds' => 'integer',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
