<?php

namespace Database\Seeders;

use App\Models\Bed;
use App\Models\BedCategory;
use App\Models\BedStatusHistory;
use App\Models\Hospital;
use App\Models\Municipality;
use App\Models\Room;
use App\Models\Service;
use App\Models\Ward;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class HospitalSeeder extends Seeder
{
    public function run(): void
    {
        $serviceIds = Service::query()->pluck('id', 'name');
        $categoryIds = BedCategory::query()->pluck('id', 'slug');

        $layouts = [
            'big' => [
                ['name' => 'Emergency Room', 'code' => 'ER', 'ward_type' => 'er', 'floor' => 'Ground floor', 'rooms' => 3, 'beds' => 4, 'category' => 'emergency-room'],
                ['name' => 'Intensive Care Unit', 'code' => 'ICU', 'ward_type' => 'icu', 'floor' => '2nd floor', 'rooms' => 2, 'beds' => 3, 'category' => 'intensive-care'],
                ['name' => 'Medical Ward', 'code' => 'MW', 'ward_type' => 'inpatient', 'floor' => '3rd floor', 'rooms' => 3, 'beds' => 6, 'category' => 'medical-ward'],
                ['name' => 'Surgical Ward', 'code' => 'SW', 'ward_type' => 'inpatient', 'floor' => '4th floor', 'rooms' => 2, 'beds' => 5, 'category' => 'surgical-ward'],
                ['name' => 'Pediatric Ward', 'code' => 'PW', 'ward_type' => 'inpatient', 'floor' => '2nd floor', 'rooms' => 2, 'beds' => 5, 'category' => 'pediatric-ward'],
                ['name' => 'Obstetric Ward', 'code' => 'OB', 'ward_type' => 'maternity', 'floor' => '5th floor', 'rooms' => 2, 'beds' => 4, 'category' => 'obstetric-ward'],
                ['name' => 'Isolation Room', 'code' => 'ISO', 'ward_type' => 'isolation', 'floor' => '6th floor', 'rooms' => 1, 'beds' => 2, 'category' => 'isolation-room'],
            ],
            'medium' => [
                ['name' => 'Emergency Room', 'code' => 'ER', 'ward_type' => 'er', 'floor' => 'Ground floor', 'rooms' => 2, 'beds' => 3, 'category' => 'emergency-room'],
                ['name' => 'Intensive Care Unit', 'code' => 'ICU', 'ward_type' => 'icu', 'floor' => '2nd floor', 'rooms' => 1, 'beds' => 3, 'category' => 'intensive-care'],
                ['name' => 'Medical Ward', 'code' => 'MW', 'ward_type' => 'inpatient', 'floor' => '3rd floor', 'rooms' => 2, 'beds' => 5, 'category' => 'medical-ward'],
                ['name' => 'Surgical Ward', 'code' => 'SW', 'ward_type' => 'inpatient', 'floor' => '3rd floor', 'rooms' => 2, 'beds' => 4, 'category' => 'surgical-ward'],
                ['name' => 'Pediatric Ward', 'code' => 'PW', 'ward_type' => 'inpatient', 'floor' => '2nd floor', 'rooms' => 1, 'beds' => 4, 'category' => 'pediatric-ward'],
                ['name' => 'Obstetric Ward', 'code' => 'OB', 'ward_type' => 'maternity', 'floor' => '2nd floor', 'rooms' => 1, 'beds' => 4, 'category' => 'obstetric-ward'],
                ['name' => 'Isolation Room', 'code' => 'ISO', 'ward_type' => 'isolation', 'floor' => '4th floor', 'rooms' => 1, 'beds' => 2, 'category' => 'isolation-room'],
            ],
            'small' => [
                ['name' => 'Emergency Room', 'code' => 'ER', 'ward_type' => 'er', 'floor' => 'Ground floor', 'rooms' => 1, 'beds' => 3, 'category' => 'emergency-room'],
                ['name' => 'Intensive Care Unit', 'code' => 'ICU', 'ward_type' => 'icu', 'floor' => '2nd floor', 'rooms' => 1, 'beds' => 2, 'category' => 'intensive-care'],
                ['name' => 'Medical Ward', 'code' => 'MW', 'ward_type' => 'inpatient', 'floor' => '2nd floor', 'rooms' => 1, 'beds' => 4, 'category' => 'medical-ward'],
                ['name' => 'Surgical Ward', 'code' => 'SW', 'ward_type' => 'inpatient', 'floor' => '2nd floor', 'rooms' => 1, 'beds' => 4, 'category' => 'surgical-ward'],
                ['name' => 'Pediatric Ward', 'code' => 'PW', 'ward_type' => 'inpatient', 'floor' => '3rd floor', 'rooms' => 1, 'beds' => 3, 'category' => 'pediatric-ward'],
                ['name' => 'Obstetric Ward', 'code' => 'OB', 'ward_type' => 'maternity', 'floor' => '3rd floor', 'rooms' => 1, 'beds' => 3, 'category' => 'obstetric-ward'],
            ],
        ];

        $extras = [
            'DRMC-1101' => [
                ['name' => 'Burn Unit', 'code' => 'BU', 'ward_type' => 'burn', 'floor' => '4th floor', 'rooms' => 1, 'beds' => 2, 'category' => 'burn-unit'],
                ['name' => 'Dialysis Unit', 'code' => 'DIA', 'ward_type' => 'dialysis', 'floor' => 'Ground floor', 'rooms' => 1, 'beds' => 3, 'category' => 'dialysis-chair'],
            ],
            'SPMC-1102' => [
                ['name' => 'Neonatal ICU', 'code' => 'NICU', 'ward_type' => 'nicu', 'floor' => '3rd floor', 'rooms' => 1, 'beds' => 4, 'category' => 'neonatal-icu'],
                ['name' => 'Burn Unit', 'code' => 'BU', 'ward_type' => 'burn', 'floor' => '5th floor', 'rooms' => 1, 'beds' => 2, 'category' => 'burn-unit'],
                ['name' => 'Dialysis Unit', 'code' => 'DIA', 'ward_type' => 'dialysis', 'floor' => 'Ground floor', 'rooms' => 1, 'beds' => 4, 'category' => 'dialysis-chair'],
            ],
            'DDH-1103' => [
                ['name' => 'Dialysis Unit', 'code' => 'DIA', 'ward_type' => 'dialysis', 'floor' => 'Ground floor', 'rooms' => 1, 'beds' => 3, 'category' => 'dialysis-chair'],
            ],
        ];

        $hospitals = [
            [
                'municipality' => ['Davao del Norte', 'Tagum City'],
                'hospital_code' => 'DRMC-1101',
                'name' => 'Davao Regional Medical Center',
                'short_name' => 'DRMC',
                'facility_type' => 'tertiary',
                'ownership_type' => 'government',
                'address' => 'Apokon Road, Tagum City, Davao del Norte',
                'phone' => '(084) 216-1234',
                'email' => 'info@drmc.test',
                'bed_capacity' => 400,
                'status' => 'operational',
                'notes' => 'Regional referral center for Davao del Norte and neighboring provinces.',
                'size' => 'big',
                'fresh_minutes' => 5,
                'services' => ['Emergency Medicine', 'Internal Medicine', 'Pediatrics', 'Obstetrics and Gynecology', 'General Surgery', 'Orthopedics', 'Cardiology', 'Neurology', 'Oncology', 'Dialysis', 'Burn Care', 'Trauma Care', 'Infectious Disease', 'Pulmonology'],
            ],
            [
                'municipality' => ['Davao del Sur', 'Davao City'],
                'hospital_code' => 'SPMC-1102',
                'name' => 'Southern Philippines Medical Center',
                'short_name' => 'SPMC',
                'facility_type' => 'tertiary',
                'ownership_type' => 'government',
                'address' => 'J.P. Laurel Avenue, Davao City',
                'phone' => '(082) 227-2731',
                'email' => 'info@spmc.test',
                'bed_capacity' => 1200,
                'status' => 'operational',
                'notes' => 'National specialty center and apex referral hospital for Mindanao.',
                'size' => 'big',
                'fresh_minutes' => 10,
                'services' => ['Emergency Medicine', 'Internal Medicine', 'Pediatrics', 'Obstetrics and Gynecology', 'General Surgery', 'Orthopedics', 'Cardiology', 'Neurology', 'Oncology', 'Dialysis', 'Burn Care', 'Neonatal Intensive Care', 'Trauma Care', 'Infectious Disease', 'Psychiatry', 'Pulmonology'],
            ],
            [
                'municipality' => ['Davao del Sur', 'Davao City'],
                'hospital_code' => 'DDH-1103',
                'name' => 'Davao Doctors Hospital',
                'short_name' => 'Davao Doctors',
                'facility_type' => 'tertiary',
                'ownership_type' => 'private',
                'address' => '118 E. Quirino Avenue, Davao City',
                'phone' => '(082) 222-8000',
                'email' => 'info@davaodoctors.test',
                'bed_capacity' => 250,
                'status' => 'operational',
                'notes' => 'Private tertiary hospital with advanced cardiac and surgical services.',
                'size' => 'medium',
                'fresh_minutes' => 30,
                'services' => ['Emergency Medicine', 'Internal Medicine', 'Pediatrics', 'Obstetrics and Gynecology', 'General Surgery', 'Orthopedics', 'Cardiology', 'Neurology', 'Oncology', 'Dialysis', 'Pulmonology'],
            ],
            [
                'municipality' => ['Davao del Sur', 'Davao City'],
                'hospital_code' => 'BMH-1104',
                'name' => 'Brokenshire Memorial Hospital',
                'short_name' => 'Brokenshire',
                'facility_type' => 'secondary',
                'ownership_type' => 'private',
                'address' => 'Magsaysay Avenue, Davao City',
                'phone' => '(082) 224-1411',
                'email' => 'info@brokenshire.test',
                'bed_capacity' => 150,
                'status' => 'operational',
                'notes' => 'Private secondary hospital serving central Davao City.',
                'size' => 'small',
                'fresh_minutes' => 45,
                'services' => ['Emergency Medicine', 'Internal Medicine', 'Pediatrics', 'Obstetrics and Gynecology', 'General Surgery'],
            ],
            [
                'municipality' => ['Davao del Norte', 'Tagum City'],
                'hospital_code' => 'DNPH-1105',
                'name' => 'Davao del Norte Provincial Hospital',
                'short_name' => 'DN Provincial',
                'facility_type' => 'secondary',
                'ownership_type' => 'government',
                'address' => 'National Highway, Tagum City, Davao del Norte',
                'phone' => '(084) 216-5000',
                'email' => 'info@dnph.test',
                'bed_capacity' => 100,
                'status' => 'limited',
                'notes' => 'Provincial hospital with limited ICU capacity during renovation.',
                'size' => 'medium',
                'fresh_minutes' => 90,
                'services' => ['Emergency Medicine', 'Internal Medicine', 'Pediatrics', 'Obstetrics and Gynecology', 'General Surgery'],
            ],
            [
                'municipality' => ['Davao Oriental', 'Mati City'],
                'hospital_code' => 'DOPMC-1106',
                'name' => 'Davao Oriental Provincial Medical Center',
                'short_name' => 'DOPMC',
                'facility_type' => 'secondary',
                'ownership_type' => 'government',
                'address' => 'Mati City, Davao Oriental',
                'phone' => '(087) 388-1234',
                'email' => 'info@dopmc.test',
                'bed_capacity' => 120,
                'status' => 'operational',
                'notes' => 'Provincial referral hospital for Davao Oriental.',
                'size' => 'medium',
                'fresh_minutes' => 120,
                'services' => ['Emergency Medicine', 'Internal Medicine', 'Pediatrics', 'Obstetrics and Gynecology', 'General Surgery', 'Orthopedics'],
            ],
            [
                'municipality' => ['Davao de Oro', 'Nabunturan'],
                'hospital_code' => 'DOPH-1107',
                'name' => 'Davao de Oro Provincial Hospital',
                'short_name' => 'DO Provincial',
                'facility_type' => 'secondary',
                'ownership_type' => 'government',
                'address' => 'Nabunturan, Davao de Oro',
                'phone' => '(084) 376-1234',
                'email' => 'info@doph.test',
                'bed_capacity' => 100,
                'status' => 'operational',
                'notes' => 'Provincial hospital serving Davao de Oro municipalities.',
                'size' => 'small',
                'fresh_minutes' => 8,
                'services' => ['Emergency Medicine', 'Internal Medicine', 'Pediatrics', 'Obstetrics and Gynecology', 'General Surgery'],
            ],
            [
                'municipality' => ['Davao Occidental', 'Malita'],
                'hospital_code' => 'DOCPH-1108',
                'name' => 'Davao Occidental Provincial Hospital',
                'short_name' => 'DOC Provincial',
                'facility_type' => 'secondary',
                'ownership_type' => 'government',
                'address' => 'Malita, Davao Occidental',
                'phone' => '(082) 237-1234',
                'email' => 'info@docph.test',
                'bed_capacity' => 75,
                'status' => 'limited',
                'notes' => 'Provincial hospital with limited specialty services; referrals to SPMC and DRMC.',
                'size' => 'small',
                'fresh_minutes' => 55,
                'services' => ['Emergency Medicine', 'Internal Medicine', 'Pediatrics', 'Obstetrics and Gynecology'],
            ],
        ];

        $statusPattern = ['available', 'available', 'occupied', 'available', 'occupied', 'cleaning', 'available', 'occupied', 'out_of_service', 'available'];
        $globalBedIndex = 0;

        foreach ($hospitals as $index => $data) {
            [$provinceName, $municipalityName] = $data['municipality'];

            $municipality = Municipality::query()
                ->where('name', $municipalityName)
                ->whereHas('province', fn ($query) => $query->where('name', $provinceName))
                ->firstOrFail();

            $hospital = Hospital::query()->updateOrCreate(
                ['hospital_code' => $data['hospital_code']],
                [
                    'municipality_id' => $municipality->id,
                    'name' => $data['name'],
                    'short_name' => $data['short_name'],
                    'facility_type' => $data['facility_type'],
                    'ownership_type' => $data['ownership_type'],
                    'address' => $data['address'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'bed_capacity' => $data['bed_capacity'],
                    'status' => $data['status'],
                    'notes' => $data['notes'],
                    'profile_updated_at' => now(),
                ]
            );

            $serviceIdsForHospital = collect($data['services'])
                ->map(fn (string $name) => $serviceIds[$name] ?? null)
                ->filter()
                ->values()
                ->all();

            $hospital->services()->sync($serviceIdsForHospital);

            $updatedAt = now()->subMinutes($data['fresh_minutes']);
            $layout = array_merge($layouts[$data['size']], $extras[$data['hospital_code']] ?? []);

            foreach ($layout as $wardLayout) {
                $ward = Ward::query()->updateOrCreate(
                    ['hospital_id' => $hospital->id, 'name' => $wardLayout['name']],
                    [
                        'ward_type' => $wardLayout['ward_type'],
                        'floor' => $wardLayout['floor'],
                        'active' => true,
                    ]
                );

                for ($roomNumber = 1; $roomNumber <= $wardLayout['rooms']; $roomNumber++) {
                    $room = Room::query()->updateOrCreate(
                        ['ward_id' => $ward->id, 'room_code' => $wardLayout['code'].'-'.$roomNumber],
                        ['name' => $wardLayout['name'].' Room '.$roomNumber, 'active' => true]
                    );

                    for ($bedNumber = 0; $bedNumber < $wardLayout['beds']; $bedNumber++) {
                        $globalBedIndex++;
                        $status = $statusPattern[($globalBedIndex + $index) % count($statusPattern)];
                        $letter = chr(65 + $bedNumber);
                        $bedCode = $wardLayout['code'].'-'.$roomNumber.'-'.$letter;

                        $bed = Bed::query()->firstOrCreate(
                            ['hospital_id' => $hospital->id, 'bed_code' => $bedCode],
                            [
                                'room_id' => $room->id,
                                'bed_category_id' => $categoryIds[$wardLayout['category']],
                                'status' => $status,
                                'notes' => match ($status) {
                                    'out_of_service' => 'Scheduled maintenance',
                                    'cleaning' => 'Terminal cleaning in progress',
                                    default => null,
                                },
                                'active' => true,
                            ]
                        );

                        if (! $bed->wasRecentlyCreated) {
                            continue;
                        }

                        $bed->forceFill(['created_at' => $updatedAt, 'updated_at' => $updatedAt])->saveQuietly();

                        BedStatusHistory::query()->create([
                            'bed_id' => $bed->id,
                            'hospital_id' => $hospital->id,
                            'old_status' => null,
                            'new_status' => $status,
                            'reason' => match ($status) {
                                'occupied' => 'Initial report — patient admitted',
                                'cleaning' => 'Initial report — terminal cleaning',
                                'out_of_service' => 'Initial report — scheduled maintenance',
                                default => 'Initial availability report',
                            },
                            'changed_by' => null,
                            'changed_at' => $updatedAt,
                        ]);
                    }
                }
            }

            $hospital->forceFill(['created_at' => $updatedAt, 'updated_at' => $updatedAt])->saveQuietly();
        }
    }
}
