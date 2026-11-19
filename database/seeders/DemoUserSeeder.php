<?php

namespace Database\Seeders;

use App\Models\Hospital;
use App\Models\HospitalUserMembership;
use App\Models\LoginLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('bhn.demo_password');

        if (blank($password)) {
            throw new RuntimeException('Set BHN_DEMO_PASSWORD before running DemoUserSeeder.');
        }
        $roles = Role::query()->pluck('id', 'slug');
        $hospitals = Hospital::query()->pluck('id', 'hospital_code');

        $users = [
            [
                'email' => 'superadmin@bhn.test',
                'name' => 'Network Super Administrator',
                'phone' => '0917 100 0001',
                'role' => 'super_admin',
                'hospital' => null,
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
                'email' => 'referral@bhn.test',
                'name' => 'BPMC Hospital Staff',
                'phone' => '0917 100 0008',
                'role' => 'hospital_staff',
                'hospital' => 'BPMC-1013',
            ],
            [
                'email' => 'admission@vcmc.bhn.test',
                'name' => 'VCMC Hospital Staff',
                'phone' => '0917 100 0009',
                'role' => 'hospital_staff',
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
                'name' => 'Maramag District Hospital Staff',
                'phone' => '0917 100 0013',
                'role' => 'hospital_staff',
                'hospital' => 'MDH-1017',
            ],
            [
                'email' => 'referral@qmh.bhn.test',
                'name' => 'Quezon Municipal Hospital Staff',
                'phone' => '0917 100 0014',
                'role' => 'hospital_staff',
                'hospital' => 'QMH-1020',
            ],
            [
                'email' => 'admin@dcmh.bhn.test',
                'name' => 'Don Carlos Municipal Hospital Admin',
                'phone' => '0917 100 0015',
                'role' => 'hospital_admin',
                'hospital' => 'DCMH-1018',
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
