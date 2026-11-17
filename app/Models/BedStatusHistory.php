<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BedStatusHistory extends Model
{
    protected $table = 'bed_status_history';

    public $timestamps = false;

    protected $fillable = [
        'bed_id', 'hospital_id', 'old_status', 'new_status', 'reason',
        'changed_by', 'changed_at',
    ];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
