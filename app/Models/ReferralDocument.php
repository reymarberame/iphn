<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralDocument extends Model
{
    protected $fillable = ['referral_id', 'storage_path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
