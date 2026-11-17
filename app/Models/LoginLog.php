<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'email', 'ip_address', 'user_agent', 'success', 'created_at'];

    protected function casts(): array
    {
        return ['success' => 'boolean', 'created_at' => 'datetime'];
    }
}
