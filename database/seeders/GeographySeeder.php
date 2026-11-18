<?php

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Seeder;

class GeographySeeder extends Seeder
{
    /**
     * BHN deployment scope: Bukidnon only.
     *
     * The application keeps the region/province data model so reports and
     * administrative scopes remain meaningful, but only Region X and the
     * Province of Bukidnon are seeded. All 22 Bukidnon LGUs are included:
     * 20 municipalities and the component cities of Malaybalay and Valencia.
     */
    public function run(): void
    {
        $region = Region::query()->updateOrCreate(
            ['code' => '1000000000'],
            ['name' => 'Northern Mindanao', 'short_name' => 'Region X']
        );

        $province = Province::query()->updateOrCreate(
            ['code' => '1013000000'],
            ['region_id' => $region->id, 'name' => 'Bukidnon']
        );

        $municipalities = [
            ['name' => 'Baungon', 'type' => 'municipality'],
            ['name' => 'Cabanglasan', 'type' => 'municipality'],
            ['name' => 'Damulog', 'type' => 'municipality'],
            ['name' => 'Dangcagan', 'type' => 'municipality'],
            ['name' => 'Don Carlos', 'type' => 'municipality'],
            ['name' => 'Impasug-ong', 'type' => 'municipality'],
            ['name' => 'Kadingilan', 'type' => 'municipality'],
            ['name' => 'Kalilangan', 'type' => 'municipality'],
            ['name' => 'Kibawe', 'type' => 'municipality'],
            ['name' => 'Kitaotao', 'type' => 'municipality'],
            ['name' => 'Lantapan', 'type' => 'municipality'],
            ['name' => 'Libona', 'type' => 'municipality'],
            ['name' => 'Malaybalay City', 'type' => 'city'],
            ['name' => 'Malitbog', 'type' => 'municipality'],
            ['name' => 'Manolo Fortich', 'type' => 'municipality'],
            ['name' => 'Maramag', 'type' => 'municipality'],
            ['name' => 'Pangantucan', 'type' => 'municipality'],
            ['name' => 'Quezon', 'type' => 'municipality'],
            ['name' => 'San Fernando', 'type' => 'municipality'],
            ['name' => 'Sumilao', 'type' => 'municipality'],
            ['name' => 'Talakag', 'type' => 'municipality'],
            ['name' => 'Valencia City', 'type' => 'city'],
        ];

        foreach ($municipalities as $index => $municipality) {
            Municipality::query()->updateOrCreate(
                ['province_id' => $province->id, 'name' => $municipality['name']],
                [
                    'code' => 'BUK-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                    'type' => $municipality['type'],
                ]
            );
        }
    }
}
