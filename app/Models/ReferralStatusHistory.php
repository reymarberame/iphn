<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralStatusHistory extends Model
{
    protected $table = 'referral_status_history';

    public $timestamps = false;

    protected $fillable = ['referral_id', 'old_status', 'new_status', 'reason', 'changed_by', 'changed_at'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
