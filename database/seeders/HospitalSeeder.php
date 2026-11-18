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

class HospitalSeeder extends Seeder
{
    /**
     * Representative Bukidnon hospital network.
     *
     * Facility names and profiles are demonstrative and use a mix of
     * provincial, city, district/municipal, and private hospitals so the
     * seeded data exercises every ownership and capability level. Replace
     * the details with official facility records before production use.
     */
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
            'BPMC-1013' => [
                ['name' => 'Burn Unit', 'code' => 'BU', 'ward_type' => 'burn', 'floor' => '4th floor', 'rooms' => 1, 'beds' => 2, 'category' => 'burn-unit'],
                ['name' => 'Dialysis Unit', 'code' => 'DIA', 'ward_type' => 'dialysis', 'floor' => 'Ground floor', 'rooms' => 1, 'beds' => 3, 'category' => 'dialysis-chair'],
            ],
            'BDH-1014' => [
                ['name' => 'Neonatal ICU', 'code' => 'NICU', 'ward_type' => 'nicu', 'floor' => '3rd floor', 'rooms' => 1, 'beds' => 3, 'category' => 'neonatal-icu'],
                ['name' => 'Dialysis Unit', 'code' => 'DIA', 'ward_type' => 'dialysis', 'floor' => 'Ground floor', 'rooms' => 1, 'beds' => 3, 'category' => 'dialysis-chair'],
            ],
            'VCMC-1015' => [
                ['name' => 'Neonatal ICU', 'code' => 'NICU', 'ward_type' => 'nicu', 'floor' => '3rd floor', 'rooms' => 1, 'beds' => 3, 'category' => 'neonatal-icu'],
                ['name' => 'Dialysis Unit', 'code' => 'DIA', 'ward_type' => 'dialysis', 'floor' => 'Ground floor', 'rooms' => 1, 'beds' => 3, 'category' => 'dialysis-chair'],
            ],
            'MDH-1017' => [
                ['name' => 'Dialysis Unit', 'code' => 'DIA', 'ward_type' => 'dialysis', 'floor' => 'Ground floor', 'rooms' => 1, 'beds' => 2, 'category' => 'dialysis-chair'],
            ],
        ];

        $hospitals = [
            [
                'municipality' => ['Bukidnon', 'Malaybalay City'],
                'hospital_code' => 'BPMC-1013',
                'name' => 'Bukidnon Provincial Medical Center',
                'short_name' => 'BPMC',
                'facility_type' => 'tertiary',
                'ownership_type' => 'government',
                'address' => 'Fortich Street, Malaybalay City, Bukidnon',
                'phone' => '(088) 813-1234',
                'email' => 'info@bpmc.bhn.test',
                'bed_capacity' => 400,
                'status' => 'operational',
                'notes' => 'Provincial referral center for Bukidnon and the primary receiving facility for complex cases.',
                'size' => 'big',
                'fresh_minutes' => 5,
                'services' => ['Emergency Medicine', 'Internal Medicine', 'Pediatrics', 'Obstetrics and Gynecology', 'General Surgery', 'Orthopedics', 'Cardiology', 'Neurology', 'Oncology', 'Dialysis', 'Burn Care', 'Trauma Care', 'Infectious Disease', 'Pulmonology'],
            ],
            [
                'municipality' => ['Bukidnon', 'Malaybalay City'],
                'hospital_code' => 'BDH-1014',
                'name' => 'Bukidnon Doctors Hospital',
                'short_name' => 'Bukidnon Doctors',
                'facility_type' => 'tertiary',
                'ownership_type' => 'private',
                'address' => 'Sayre Highway, Malaybalay City, Bukidnon',
                'phone' => '(088) 813-5678',
                'email' => 'info@bdh.bhn.test',
                'bed_capacity' => 180,
                'status' => 'operational',
                'notes' => 'Private tertiary hospital serving Malaybalay City and nearby municipalities.',
                'size' => 'medium',
                'fresh_minutes' => 30,
                'services' => ['Emergency Medicine', 'Internal Medicine', 'Pediatrics', 'Obstetrics and Gynecology', 'General Surgery', 'Orthopedics', 'Cardiology', 'Neurology', 'Oncology', 'Dialysis', 'Neonatal Intensive Care', 'Pulmonology'],
            ],
            [
                'municipality' => ['Bukidnon', 'Valencia City'],
                'hospital_code' => 'VCMC-1015',
                'name' => 'Valencia City Medical Center',
                'short_name' => 'VCMC',
                'facility_type' => 'secondary',
                'ownership_type' => 'government',
                'address' => 'Sayre Highway, Valencia City, Bukidnon',
                'phone' => '(088) 828-1234',
                'email' => 'info@vcmc.bhn.test',
                'bed_capacity' => 200,
                'status' => 'operational',
                'notes' => 'City hospital serving Valencia City and the southern Bukidnon corridor.',
                'size' => 'medium',
                'fresh_minutes' => 10,
                'services' => ['Emergency Medicine', 'Internal Medicine', 'Pediatrics', 'Obstetrics and Gynecology', 'General Surgery', 'Orthopedics', 'Dialysis', 'Neonatal Intensive Care'],
            ],
            [
                'municipality' => ['Bukidnon', 'Valencia City'],
                'hospital_code' => 'VDH-1016',
                'name' => 'Valencia Doctors Hospital',
                'short_name' => 'Valencia Doctors',
                'facility_type' => 'secondary',
                'ownership_type' => 'private',
                'address' => 'Poblacion, Valencia City, Bukidnon',
                'phone' => '(088) 828-4567',
                'email' => 'info@vdh.bhn.test',
                'bed_capacity' => 90,
                'status' => 'operational',
                'notes' => 'Private secondary hospital serving Valencia City and neighboring LGUs.',
                'size' => 'small',
                'fresh_minutes' => 45,
                'services' => ['Emergency Medicine', 'Internal Medicine', 'Pediatrics', 'Obstetrics and Gynecology', 'General Surgery'],
            ],
            [
                'municipality' => ['Bukidnon', 'Maramag'],
                'hospital_code' => 'MDH-1017',
                'name' => 'Maramag District Hospital',
                'short_name' => 'Maramag District',
                'facility_type' => 'secondary',
                'ownership_type' => 'government',
                'address' => 'Poblacion, Maramag, Bukidnon',
                'phone' => '(088) 826-1234',
                'email' => 'info@mdh.bhn.test',
                'bed_capacity' => 75,
                'status' => 'limited',
                'notes' => 'District hospital with limited ICU capacity during renovation; complex cases refer to BPMC or VCMC.',
                'size' => 'medium',
                'fresh_minutes' => 90,
                'services' => ['Emergency Medicine', 'Internal Medicine', 'Pediatrics', 'Obstetrics and Gynecology', 'General Surgery', 'Dialysis'],
            ],
            [
                'municipality' => ['Bukidnon', 'Don Carlos'],
                'hospital_code' => 'DCMH-1018',
                'name' => 'Don Carlos Municipal Hospital',
                'short_name' => 'Don Carlos Municipal',
                'facility_type' => 'secondary',
                'ownership_type' => 'government',
                'address' => 'Poblacion, Don Carlos, Bukidnon',
                'phone' => '(088) 829-1234',
                'email' => 'info@dcmh.bhn.test',
                'bed_capacity' => 50,
                'status' => 'operational',
                'notes' => 'Municipal hospital providing basic emergency and inpatient care.',
                'size' => 'small',
                'fresh_minutes' => 8,
                'services' => ['Emergency Medicine', 'Internal Medicine', 'Pediatrics', 'Obstetrics and Gynecology'],
            ],
            [
                'municipality' => ['Bukidnon', 'Manolo Fortich'],
                'hospital_code' => 'MFCH-1019',
                'name' => 'Manolo Fortich Community Hospital',
                'short_name' => 'Manolo Fortich',
                'facility_type' => 'secondary',
                'ownership_type' => 'government',
                'address' => 'Poblacion, Manolo Fortich, Bukidnon',
                'phone' => '(088) 827-1234',
                'email' => 'info@mfch.bhn.test',
                'bed_capacity' => 60,
                'status' => 'operational',
                'notes' => 'Community hospital serving Manolo Fortich and the northern Bukidnon municipalities.',
                'size' => 'small',
                'fresh_minutes' => 55,
                'services' => ['Emergency Medicine', 'Internal Medicine', 'Pediatrics', 'Obstetrics and Gynecology', 'General Surgery'],
            ],
            [
                'municipality' => ['Bukidnon', 'Quezon'],
                'hospital_code' => 'QMH-1020',
                'name' => 'Quezon Municipal Hospital',
                'short_name' => 'Quezon Municipal',
                'facility_type' => 'secondary',
                'ownership_type' => 'government',
                'address' => 'Poblacion, Quezon, Bukidnon',
                'phone' => '(088) 825-1234',
                'email' => 'info@qmh.bhn.test',
                'bed_capacity' => 40,
                'status' => 'limited',
                'notes' => 'Municipal hospital with limited specialty services; referrals coordinated with BPMC and VCMC.',
                'size' => 'small',
                'fresh_minutes' => 120,
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
