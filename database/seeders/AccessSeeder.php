<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccessSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'hospitals.manage', 'group' => 'Hospitals', 'description' => 'Edit hospital profiles and service listings.'],
            ['name' => 'hospitals.register', 'group' => 'Hospitals', 'description' => 'Register new hospitals in the network.'],
            ['name' => 'beds.view', 'group' => 'Bed availability', 'description' => 'View bed availability and update history.'],
            ['name' => 'beds.update', 'group' => 'Bed availability', 'description' => 'Report individual bed status changes.'],
            ['name' => 'beds.manage', 'group' => 'Bed availability', 'description' => 'Set up wards, rooms, and bed inventory.'],
            ['name' => 'beds.reserve', 'group' => 'Bed availability', 'description' => 'Hold a bed for an accepted referral.'],
            ['name' => 'referrals.view', 'group' => 'Referrals', 'description' => 'View referrals for the user’s hospital.'],
            ['name' => 'referrals.create', 'group' => 'Referrals', 'description' => 'Create and submit referrals.'],
            ['name' => 'referrals.respond', 'group' => 'Referrals', 'description' => 'Accept, decline, or request information on referrals.'],
            ['name' => 'transfers.view', 'group' => 'Transfers', 'description' => 'View transfer coordination records.'],
            ['name' => 'transfers.manage', 'group' => 'Transfers', 'description' => 'Schedule and update transfer status.'],
            ['name' => 'reports.view', 'group' => 'Reports', 'description' => 'View operational reports.'],
            ['name' => 'reports.national', 'group' => 'Reports', 'description' => 'View network-wide aggregate reports.'],
            ['name' => 'users.manage', 'group' => 'Administration', 'description' => 'Provision and manage user accounts.'],
            ['name' => 'audit.view', 'group' => 'Administration', 'description' => 'Review audit and login records.'],
            ['name' => 'settings.manage', 'group' => 'Administration', 'description' => 'Change platform settings and thresholds.'],
        ];

        $permissionIds = [];

        foreach ($permissions as $permission) {
            $model = Permission::query()->updateOrCreate(['name' => $permission['name']], $permission);
            $permissionIds[$permission['name']] = $model->id;
        }

        $all = array_keys($permissionIds);

        $roles = [
            [
                'name' => 'Super Administrator',
                'slug' => 'super_admin',
                'scope_type' => 'network',
                'description' => 'Full platform access, including settings and all network records.',
                'permissions' => $all,
            ],
            [
                'name' => 'Network Administrator',
                'slug' => 'national_network_admin',
                'scope_type' => 'network',
                'description' => 'Manages the hospital network, users, and aggregate reports.',
                'permissions' => [
                    'hospitals.manage', 'hospitals.register', 'beds.view', 'referrals.view', 'transfers.view',
                    'reports.view', 'reports.national', 'users.manage', 'audit.view', 'settings.manage',
                ],
            ],
            [
                'name' => 'Regional Administrator',
                'slug' => 'regional_admin',
                'scope_type' => 'region',
                'description' => 'Administers hospitals and users within an assigned region.',
                'permissions' => [
                    'hospitals.manage', 'hospitals.register', 'beds.view', 'referrals.view', 'transfers.view',
                    'reports.view', 'reports.national', 'users.manage', 'audit.view',
                ],
            ],
            [
                'name' => 'Provincial Administrator',
                'slug' => 'provincial_admin',
                'scope_type' => 'province',
                'description' => 'Administers hospitals and users within an assigned province.',
                'permissions' => [
                    'hospitals.manage', 'beds.view', 'referrals.view', 'transfers.view',
                    'reports.view', 'users.manage', 'audit.view',
                ],
            ],
            [
                'name' => 'Hospital Administrator',
                'slug' => 'hospital_admin',
                'scope_type' => 'hospital',
                'description' => 'Manages a hospital profile, users, bed inventory, and referral coordination.',
                'permissions' => [
                    'hospitals.manage', 'beds.view', 'beds.update', 'beds.manage', 'beds.reserve',
                    'referrals.view', 'referrals.create', 'referrals.respond', 'transfers.view', 'transfers.manage',
                    'reports.view', 'users.manage', 'audit.view',
                ],
            ],
            [
                'name' => 'Hospital Bed Manager',
                'slug' => 'bed_manager',
                'scope_type' => 'hospital',
                'description' => 'Maintains ward, room, and bed inventory and reports availability.',
                'permissions' => ['beds.view', 'beds.update', 'beds.manage', 'reports.view'],
            ],
            [
                'name' => 'ER Staff',
                'slug' => 'er_staff',
                'scope_type' => 'hospital',
                'description' => 'Reports emergency department bed availability.',
                'permissions' => ['beds.view', 'beds.update'],
            ],
            [
                'name' => 'Referral Coordinator',
                'slug' => 'referral_coordinator',
                'scope_type' => 'hospital',
                'description' => 'Coordinates outgoing referrals and transfer logistics.',
                'permissions' => [
                    'beds.view', 'beds.reserve', 'referrals.view', 'referrals.create', 'referrals.respond',
                    'transfers.view', 'transfers.manage', 'reports.view',
                ],
            ],
            [
                'name' => 'Admission Staff',
                'slug' => 'admission_staff',
                'scope_type' => 'hospital',
                'description' => 'Receives referrals and holds beds for accepted patients.',
                'permissions' => ['beds.view', 'beds.reserve', 'referrals.view', 'referrals.respond', 'transfers.view'],
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $roleData['slug']],
                [
                    'name' => $roleData['name'],
                    'scope_type' => $roleData['scope_type'],
                    'description' => $roleData['description'],
                ]
            );

            $role->permissions()->sync(array_map(
                fn (string $name) => $permissionIds[$name],
                $roleData['permissions']
            ));
        }
    }
}
