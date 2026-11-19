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
                'description' => 'Full platform access across the hospital network.',
                'permissions' => $all,
            ],
            [
                'name' => 'Hospital Administrator',
                'slug' => 'hospital_admin',
                'scope_type' => 'hospital',
                'description' => 'Manages a hospital profile, users, bed inventory, referrals, and transfers.',
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
                'name' => 'Hospital Staff',
                'slug' => 'hospital_staff',
                'scope_type' => 'hospital',
                'description' => 'Coordinates referrals and transfers, reports bed availability, and holds receiving beds.',
                'permissions' => [
                    'beds.view', 'beds.update', 'beds.reserve',
                    'referrals.view', 'referrals.create', 'referrals.respond',
                    'transfers.view', 'transfers.manage', 'reports.view',
                ],
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
