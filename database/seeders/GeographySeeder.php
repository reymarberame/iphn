<?php

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GeographySeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            ['code' => 'R01', 'name' => 'Ilocos Region', 'short_name' => 'Region I', 'provinces' => [
                'Ilocos Norte' => 'Laoag City', 'Ilocos Sur' => 'Vigan City', 'La Union' => 'San Fernando City', 'Pangasinan' => 'Lingayen',
            ]],
            ['code' => 'R02', 'name' => 'Cagayan Valley', 'short_name' => 'Region II', 'provinces' => [
                'Batanes' => 'Basco', 'Cagayan' => 'Tuguegarao City', 'Isabela' => 'Ilagan City', 'Nueva Vizcaya' => 'Bayombong', 'Quirino' => 'Cabarroguis',
            ]],
            ['code' => 'R03', 'name' => 'Central Luzon', 'short_name' => 'Region III', 'provinces' => [
                'Aurora' => 'Baler', 'Bataan' => 'Balanga City', 'Bulacan' => 'Malolos City', 'Nueva Ecija' => 'Palayan City',
                'Pampanga' => 'San Fernando City', 'Tarlac' => 'Tarlac City', 'Zambales' => 'Iba',
            ]],
            ['code' => 'R04', 'name' => 'CALABARZON', 'short_name' => 'Region IV-A', 'provinces' => [
                'Batangas' => 'Batangas City', 'Cavite' => 'Trece Martires City', 'Laguna' => 'Santa Cruz',
                'Quezon' => 'Lucena City', 'Rizal' => 'Antipolo City',
            ]],
            ['code' => 'R05', 'name' => 'MIMAROPA Region', 'short_name' => 'MIMAROPA', 'provinces' => [
                'Marinduque' => 'Boac', 'Occidental Mindoro' => 'Mamburao', 'Oriental Mindoro' => 'Calapan City',
                'Palawan' => 'Puerto Princesa City', 'Romblon' => 'Romblon',
            ]],
            ['code' => 'R06', 'name' => 'Bicol Region', 'short_name' => 'Region V', 'provinces' => [
                'Albay' => 'Legazpi City', 'Camarines Norte' => 'Daet', 'Camarines Sur' => 'Pili', 'Catanduanes' => 'Virac',
                'Masbate' => 'Masbate City', 'Sorsogon' => 'Sorsogon City',
            ]],
            ['code' => 'R07', 'name' => 'Western Visayas', 'short_name' => 'Region VI', 'provinces' => [
                'Aklan' => 'Kalibo', 'Antique' => 'San Jose de Buenavista', 'Capiz' => 'Roxas City',
                'Guimaras' => 'Jordan', 'Iloilo' => 'Iloilo City', 'Negros Occidental' => 'Bacolod City',
            ]],
            ['code' => 'R08', 'name' => 'Central Visayas', 'short_name' => 'Region VII', 'provinces' => [
                'Bohol' => 'Tagbilaran City', 'Cebu' => 'Cebu City', 'Negros Oriental' => 'Dumaguete City', 'Siquijor' => 'Siquijor',
            ]],
            ['code' => 'R09', 'name' => 'Eastern Visayas', 'short_name' => 'Region VIII', 'provinces' => [
                'Biliran' => 'Naval', 'Eastern Samar' => 'Borongan City', 'Leyte' => 'Tacloban City',
                'Northern Samar' => 'Catarman', 'Samar' => 'Catbalogan City', 'Southern Leyte' => 'Maasin City',
            ]],
            ['code' => 'R10', 'name' => 'Zamboanga Peninsula', 'short_name' => 'Region IX', 'provinces' => [
                'Zamboanga del Norte' => 'Dipolog City', 'Zamboanga del Sur' => 'Pagadian City', 'Zamboanga Sibugay' => 'Ipil',
            ]],
            ['code' => 'R11', 'name' => 'Northern Mindanao', 'short_name' => 'Region X', 'provinces' => [
                'Bukidnon' => 'Malaybalay City', 'Camiguin' => 'Mambajao', 'Lanao del Norte' => 'Tubod',
                'Misamis Occidental' => 'Oroquieta City', 'Misamis Oriental' => 'Cagayan de Oro City',
            ]],
            ['code' => 'R12', 'name' => 'Davao Region', 'short_name' => 'Region XI', 'provinces' => [
                'Davao de Oro' => 'Nabunturan', 'Davao del Norte' => 'Tagum City', 'Davao del Sur' => 'Digos City',
                'Davao Occidental' => 'Malita', 'Davao Oriental' => 'Mati City',
            ]],
            ['code' => 'R13', 'name' => 'SOCCSKSARGEN', 'short_name' => 'Region XII', 'provinces' => [
                'Cotabato' => 'Kidapawan City', 'Sarangani' => 'Alabel', 'South Cotabato' => 'Koronadal City', 'Sultan Kudarat' => 'Isulan',
            ]],
            ['code' => 'R14', 'name' => 'Caraga', 'short_name' => 'Region XIII', 'provinces' => [
                'Agusan del Norte' => 'Cabadbaran City', 'Agusan del Sur' => 'Prosperidad', 'Dinagat Islands' => 'San Jose',
                'Surigao del Norte' => 'Surigao City', 'Surigao del Sur' => 'Tandag City',
            ]],
            ['code' => 'R15', 'name' => 'Cordillera Administrative Region', 'short_name' => 'CAR', 'provinces' => [
                'Abra' => 'Bangued', 'Apayao' => 'Kabugao', 'Benguet' => 'La Trinidad', 'Ifugao' => 'Lagawe',
                'Kalinga' => 'Tabuk City', 'Mountain Province' => 'Bontoc',
            ]],
            ['code' => 'R16', 'name' => 'National Capital Region', 'short_name' => 'NCR', 'provinces' => [
                'Metro Manila' => 'Manila',
            ]],
            ['code' => 'R17', 'name' => 'Bangsamoro Autonomous Region in Muslim Mindanao', 'short_name' => 'BARMM', 'provinces' => [
                'Basilan' => 'Lamitan City', 'Lanao del Sur' => 'Marawi City', 'Maguindanao del Norte' => 'Datu Odin Sinsuat',
                'Maguindanao del Sur' => 'Buluan', 'Sulu' => 'Jolo', 'Tawi-Tawi' => 'Bongao',
            ]],
        ];

        $extras = [
            'Metro Manila' => ['Quezon City', 'Makati City', 'Pasig City', 'Taguig City', 'Pasay City', 'Caloocan City'],
            'Davao de Oro' => ['Monkayo', 'Pantukan'],
            'Davao del Norte' => ['Panabo City', 'Island Garden City of Samal'],
            'Davao del Sur' => ['Davao City', 'Santa Cruz'],
            'Davao Occidental' => ['Jose Abad Santos'],
            'Davao Oriental' => ['Baganga', 'Mati City'],
        ];

        $provinceIndex = 0;
        $municipalityIndex = 0;

        foreach ($regions as $regionData) {
            $region = Region::query()->updateOrCreate(
                ['code' => $regionData['code']],
                ['name' => $regionData['name'], 'short_name' => $regionData['short_name']]
            );

            foreach ($regionData['provinces'] as $provinceName => $capital) {
                $provinceIndex++;
                $province = Province::query()->updateOrCreate(
                    ['code' => 'P'.str_pad((string) $provinceIndex, 3, '0', STR_PAD_LEFT)],
                    ['region_id' => $region->id, 'name' => $provinceName]
                );

                $municipalities = array_values(array_unique(array_merge([$capital], $extras[$provinceName] ?? [])));

                foreach ($municipalities as $municipalityName) {
                    $municipalityIndex++;
                    Municipality::query()->updateOrCreate(
                        ['province_id' => $province->id, 'name' => $municipalityName],
                        [
                            'code' => 'M'.str_pad((string) $municipalityIndex, 4, '0', STR_PAD_LEFT),
                            'type' => Str::endsWith($municipalityName, 'City') ? 'city' : 'municipality',
                        ]
                    );
                }
            }
        }
    }
}
