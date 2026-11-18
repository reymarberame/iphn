<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'network_name' => 'Bukidnon Hospital Network',
            'freshness_fresh_minutes' => '15',
            'freshness_aging_minutes' => '60',
            'reservation_ttl_minutes' => '120',
            'availability_disclaimer' => config('bhn.disclaimer'),
            'support_email' => 'support@bhn.test',
        ];

        foreach ($settings as $key => $value) {
            SystemSetting::put($key, $value);
        }
    }
}
