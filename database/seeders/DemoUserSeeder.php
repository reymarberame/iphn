<?php

namespace Database\Seeders;

use App\Models\AdminScope;
use App\Models\Hospital;
use App\Models\HospitalUserMembership;
use App\Models\LoginLog;
use App\Models\Province;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('bhn.demo_password');
        $roles = Role::query()->pluck('id', 'slug');
        $hospitals = Hospital::query()->pluck('id', 'hospital_code');
        $regionX = Region::query()->where('short_name', 'Region X')->firstOrFail();
        $bukidnon = Province::query()->where('name', 'Bukidnon')->firstOrFail();

        $users = [
            [
                'email' => 'superadmin@bhn.test',
                'name' => 'Network Super Administrator',
                'phone' => '0917 100 0001',
                'role' => 'super_admin',
                'hospital' => null,
                'region' => null,
                'province' => null,
            ],
            [
                'email' => 'network@bhn.test',
                'name' => 'Bukidnon Network Administrator',
                'phone' => '0917 100 0002',
                'role' => 'national_network_admin',
                'hospital' => null,
                'region' => null,
                'province' => null,
            ],
            [
                'email' => 'region10@bhn.test',
                'name' => 'Region X Administrator',
                'phone' => '0917 100 0003',
                'role' => 'regional_admin',
                'hospital' => null,
                'region' => $regionX->id,
                'province' => null,
            ],
            [
                'email' => 'province@bhn.test',
                'name' => 'Bukidnon Provincial Administrator',
                'phone' => '0917 100 0004',
                'role' => 'provincial_admin',
                'hospital' => null,
                'region' => null,
                'province' => $bukidnon->id,
            ],
            [
                'email' => 'hospitaladmin@bhn.test',
                'name' => 'Bukidnon Provincial Medical Center Admin',
                'phone' => '0917 100 0005',
                'role' => 'hospital_admin',
                'hospital' => 'BPMC-1013',
            ],
            [
                'email' => 'bedmanager@bhn.test',
                'name' => 'BPMC Bed Manager',
                'phone' => '0917 100 0006',
                'role' => 'bed_manager',
                'hospital' => 'BPMC-1013',
            ],
            [
                'email' => 'erstaff@bhn.test',
                'name' => 'BPMC ER Staff',
                'phone' => '0917 100 0007',
                'role' => 'er_staff',
                'hospital' => 'BPMC-1013',
            ],
            [
                'email' => 'referral@bhn.test',
                'name' => 'BPMC Referral Coordinator',
                'phone' => '0917 100 0008',
                'role' => 'referral_coordinator',
                'hospital' => 'BPMC-1013',
            ],
            [
                'email' => 'admission@vcmc.bhn.test',
                'name' => 'VCMC Admission Staff',
                'phone' => '0917 100 0009',
                'role' => 'admission_staff',
                'hospital' => 'VCMC-1015',
            ],
            [
                'email' => 'coordinator@vcmc.bhn.test',
                'name' => 'VCMC Referral Coordinator',
                'phone' => '0917 100 0010',
                'role' => 'referral_coordinator',
                'hospital' => 'VCMC-1015',
            ],
            [
                'email' => 'bedmanager@vcmc.bhn.test',
                'name' => 'VCMC Bed Manager',
                'phone' => '0917 100 0011',
                'role' => 'bed_manager',
                'hospital' => 'VCMC-1015',
            ],
            [
                'email' => 'admin@bdh.bhn.test',
                'name' => 'Bukidnon Doctors Hospital Admin',
                'phone' => '0917 100 0012',
                'role' => 'hospital_admin',
                'hospital' => 'BDH-1014',
            ],
            [
                'email' => 'referral@mdh.bhn.test',
                'name' => 'Maramag District Referral Coordinator',
                'phone' => '0917 100 0013',
                'role' => 'referral_coordinator',
                'hospital' => 'MDH-1017',
            ],
            [
                'email' => 'referral@qmh.bhn.test',
                'name' => 'Quezon Municipal Referral Coordinator',
                'phone' => '0917 100 0014',
                'role' => 'referral_coordinator',
                'hospital' => 'QMH-1020',
            ],
            [
                'email' => 'admin@dcmh.bhn.test',
                'name' => 'Don Carlos Municipal Hospital Admin',
                'phone' => '0917 100 0015',
                'role' => 'hospital_admin',
                'hospital' => 'DCMH-1018',
            ],
            [
                'email' => 'admin@mfch.bhn.test',
                'name' => 'Manolo Fortich Community Hospital Admin',
                'phone' => '0917 100 0016',
                'role' => 'hospital_admin',
                'hospital' => 'MFCH-1019',
            ],
            [
                'email' => 'admin@vdh.bhn.test',
                'name' => 'Valencia Doctors Hospital Admin',
                'phone' => '0917 100 0017',
                'role' => 'hospital_admin',
                'hospital' => 'VDH-1016',
            ],
        ];

        foreach ($users as $data) {
            $user = User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => $password,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );

            $roleId = $roles[$data['role']];
            $role = Role::query()->find($roleId);

            if ($role?->isNetworkScoped()) {
                $user->roles()->sync([$roleId]);

                if (! empty($data['region'])) {
                    AdminScope::query()->updateOrCreate(
                        ['user_id' => $user->id, 'region_id' => $data['region'], 'province_id' => null],
                        []
                    );
                }

                if (! empty($data['province'])) {
                    AdminScope::query()->updateOrCreate(
                        ['user_id' => $user->id, 'region_id' => null, 'province_id' => $data['province']],
                        []
                    );
                }
            } else {
                $user->roles()->sync([]);
                HospitalUserMembership::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'hospital_id' => $hospitals[$data['hospital']],
                        'role_id' => $roleId,
                    ],
                    ['status' => 'active']
                );
            }
        }

        $loginSamples = [
            ['email' => 'superadmin@bhn.test', 'minutes' => 240, 'success' => true],
            ['email' => 'bedmanager@bhn.test', 'minutes' => 180, 'success' => true],
            ['email' => 'admission@vcmc.bhn.test', 'minutes' => 120, 'success' => true],
            ['email' => 'referral@bhn.test', 'minutes' => 60, 'success' => true],
            ['email' => 'unknown@bhn.test', 'minutes' => 30, 'success' => false],
        ];

        foreach ($loginSamples as $sample) {
            $user = User::query()->where('email', $sample['email'])->first();

            LoginLog::query()->create([
                'user_id' => $user?->id,
                'email' => $sample['email'],
                'ip_address' => '192.168.10.'.random_int(10, 250),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) BHN demo',
                'success' => $sample['success'],
                'created_at' => now()->subMinutes($sample['minutes']),
            ]);
        }
    }
}
