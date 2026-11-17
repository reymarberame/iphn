<?php

namespace Database\Seeders;

use App\Models\AdminScope;
use App\Models\Hospital;
use App\Models\HospitalUserMembership;
use App\Models\LoginLog;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('iphn.demo_password');
        $roles = Role::query()->pluck('id', 'slug');
        $hospitals = Hospital::query()->pluck('id', 'hospital_code');
        $regionXI = Region::query()->where('short_name', 'Region XI')->firstOrFail();

        $users = [
            [
                'email' => 'superadmin@iphn.test',
                'name' => 'Network Super Administrator',
                'phone' => '0917 100 0001',
                'role' => 'super_admin',
                'hospital' => null,
                'region' => null,
            ],
            [
                'email' => 'network@iphn.test',
                'name' => 'National Network Administrator',
                'phone' => '0917 100 0002',
                'role' => 'national_network_admin',
                'hospital' => null,
                'region' => null,
            ],
            [
                'email' => 'region11@iphn.test',
                'name' => 'Region XI Administrator',
                'phone' => '0917 100 0003',
                'role' => 'regional_admin',
                'hospital' => null,
                'region' => $regionXI->id,
            ],
            [
                'email' => 'hospitaladmin@iphn.test',
                'name' => 'Davao Regional Medical Center Admin',
                'phone' => '0917 100 0004',
                'role' => 'hospital_admin',
                'hospital' => 'DRMC-1101',
            ],
            [
                'email' => 'bedmanager@iphn.test',
                'name' => 'DRMC Bed Manager',
                'phone' => '0917 100 0005',
                'role' => 'bed_manager',
                'hospital' => 'DRMC-1101',
            ],
            [
                'email' => 'erstaff@iphn.test',
                'name' => 'DRMC ER Staff',
                'phone' => '0917 100 0006',
                'role' => 'er_staff',
                'hospital' => 'DRMC-1101',
            ],
            [
                'email' => 'referral@iphn.test',
                'name' => 'DRMC Referral Coordinator',
                'phone' => '0917 100 0007',
                'role' => 'referral_coordinator',
                'hospital' => 'DRMC-1101',
            ],
            [
                'email' => 'admission@iphn.test',
                'name' => 'SPMC Admission Staff',
                'phone' => '0917 100 0008',
                'role' => 'admission_staff',
                'hospital' => 'SPMC-1102',
            ],
            [
                'email' => 'coordinator@spmc.test',
                'name' => 'SPMC Referral Coordinator',
                'phone' => '0917 100 0009',
                'role' => 'referral_coordinator',
                'hospital' => 'SPMC-1102',
            ],
            [
                'email' => 'bedmanager@spmc.test',
                'name' => 'SPMC Bed Manager',
                'phone' => '0917 100 0010',
                'role' => 'bed_manager',
                'hospital' => 'SPMC-1102',
            ],
            [
                'email' => 'admin@davaodoctors.test',
                'name' => 'Davao Doctors Hospital Admin',
                'phone' => '0917 100 0011',
                'role' => 'hospital_admin',
                'hospital' => 'DDH-1103',
            ],
            [
                'email' => 'referral@davnor.test',
                'name' => 'Davao del Norte Referral Coordinator',
                'phone' => '0917 100 0012',
                'role' => 'referral_coordinator',
                'hospital' => 'DNPH-1105',
            ],
            [
                'email' => 'referral@davaooriental.test',
                'name' => 'Davao Oriental Referral Coordinator',
                'phone' => '0917 100 0013',
                'role' => 'referral_coordinator',
                'hospital' => 'DOPMC-1106',
            ],
            [
                'email' => 'admin@davaodeoro.test',
                'name' => 'Davao de Oro Hospital Admin',
                'phone' => '0917 100 0014',
                'role' => 'hospital_admin',
                'hospital' => 'DOPH-1107',
            ],
            [
                'email' => 'admin@docph.test',
                'name' => 'Davao Occidental Hospital Admin',
                'phone' => '0917 100 0015',
                'role' => 'hospital_admin',
                'hospital' => 'DOCPH-1108',
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
            ['email' => 'superadmin@iphn.test', 'minutes' => 240, 'success' => true],
            ['email' => 'bedmanager@iphn.test', 'minutes' => 180, 'success' => true],
            ['email' => 'admission@iphn.test', 'minutes' => 120, 'success' => true],
            ['email' => 'referral@iphn.test', 'minutes' => 60, 'success' => true],
            ['email' => 'unknown@iphn.test', 'minutes' => 30, 'success' => false],
        ];

        foreach ($loginSamples as $sample) {
            $user = User::query()->where('email', $sample['email'])->first();

            LoginLog::query()->create([
                'user_id' => $user?->id,
                'email' => $sample['email'],
                'ip_address' => '192.168.10.'.random_int(10, 250),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) IPHN demo',
                'success' => $sample['success'],
                'created_at' => now()->subMinutes($sample['minutes']),
            ]);
        }
    }
}
