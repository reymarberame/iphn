<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GeographySeeder::class,
            AccessSeeder::class,
            CatalogSeeder::class,
            SettingsSeeder::class,
            HospitalSeeder::class,
            DemoUserSeeder::class,
            ReferralSeeder::class,
        ]);
    }
}
