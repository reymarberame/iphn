<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'updated_by'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    public static function put(string $key, mixed $value, ?int $userId = null): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $userId]
        );
    }

    public static function freshnessMinutes(): int
    {
        return (int) static::get('freshness_fresh_minutes', 15);
    }

    public static function agingMinutes(): int
    {
        return (int) static::get('freshness_aging_minutes', 60);
    }
}
