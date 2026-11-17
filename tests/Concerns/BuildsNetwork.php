<?php

namespace Tests\Concerns;

use App\Models\Bed;
use App\Models\BedCategory;
use App\Models\Hospital;
use App\Models\HospitalUserMembership;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Referral;
use App\Models\Region;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Models\Ward;
use Database\Seeders\AccessSeeder;
use Illuminate\Support\Str;

/**
 * Minimal, deterministic fixtures for the hospital network domain.
 * Every test starts from an empty database; these helpers create only the
 * geography, access, hospital, and inventory rows the test needs.
 */
trait BuildsNetwork
{
    protected ?Municipality $fixtureMunicipality = null;

    protected function seedAccess(): void
    {
        $this->seed(AccessSeeder::class);
    }

    protected function municipality(): Municipality
    {
        if ($this->fixtureMunicipality) {
            return $this->fixtureMunicipality;
        }

        $region = Region::query()->create([
            'code' => 'R-'.Str::upper(Str::random(5)),
            'name' => 'Test Region '.Str::upper(Str::random(3)),
            'short_name' => 'TR',
        ]);

        $province = Province::query()->create([
            'region_id' => $region->id,
            'code' => 'P-'.Str::upper(Str::random(5)),
            'name' => 'Test Province '.Str::upper(Str::random(3)),
        ]);

        return $this->fixtureMunicipality = Municipality::query()->create([
            'province_id' => $province->id,
            'code' => 'M-'.Str::upper(Str::random(5)),
            'name' => 'Test City '.Str::upper(Str::random(3)),
            'type' => 'city',
        ]);
    }

    protected function hospital(array $attributes = []): Hospital
    {
        return Hospital::query()->create(array_merge([
            'municipality_id' => $this->municipality()->id,
            'hospital_code' => 'HSP-'.Str::upper(Str::random(8)),
            'name' => 'Test Hospital '.Str::upper(Str::random(4)),
            'short_name' => 'TH-'.Str::upper(Str::random(3)),
            'facility_type' => 'general',
            'ownership_type' => Hospital::OWNERSHIP_GOVERNMENT,
            'status' => Hospital::STATUS_OPERATIONAL,
        ], $attributes));
    }

    /**
     * Create an active user. When a hospital is supplied, the role becomes an
     * active hospital membership; otherwise the role is a global user role.
     */
    protected function user(string $roleSlug, ?Hospital $hospital = null, array $attributes = []): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        $user = User::factory()->create(array_merge([
            'status' => 'active',
            'password' => 'Password@123',
        ], $attributes));

        if ($hospital) {
            $this->attachMembership($user, $hospital, $role);
        } else {
            $user->roles()->attach($role->id);
        }

        return $user->fresh();
    }

    protected function attachMembership(User $user, Hospital $hospital, string|Role $role): HospitalUserMembership
    {
        $role = $role instanceof Role ? $role : Role::query()->where('slug', $role)->firstOrFail();

        return HospitalUserMembership::query()->create([
            'user_id' => $user->id,
            'hospital_id' => $hospital->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    protected function bed(Hospital $hospital, array $attributes = [], ?BedCategory $category = null): Bed
    {
        $category ??= BedCategory::query()->firstOrCreate(
            ['slug' => 'ward'],
            ['name' => 'Ward', 'sort_order' => 1, 'active' => true]
        );

        $ward = Ward::query()->firstOrCreate(
            ['hospital_id' => $hospital->id, 'name' => 'Test Ward'],
            ['ward_type' => 'inpatient', 'active' => true]
        );

        $room = Room::query()->firstOrCreate(
            ['ward_id' => $ward->id, 'room_code' => 'R1'],
            ['name' => 'Room 1', 'active' => true]
        );

        return Bed::query()->create(array_merge([
            'hospital_id' => $hospital->id,
            'room_id' => $room->id,
            'bed_category_id' => $category->id,
            'bed_code' => 'B-'.Str::upper(Str::random(8)),
            'status' => Bed::STATUS_AVAILABLE,
            'active' => true,
        ], $attributes));
    }

    protected function referral(
        Hospital $referring,
        Hospital $receiving,
        User $creator,
        string $status = Referral::STATUS_DRAFT,
        array $attributes = [],
    ): Referral {
        return Referral::query()->create(array_merge([
            'referring_hospital_id' => $referring->id,
            'receiving_hospital_id' => $receiving->id,
            'patient_reference' => 'PAT-'.Str::upper(Str::random(8)),
            'patient_name' => 'Test Patient',
            'patient_age' => 42,
            'patient_sex' => 'female',
            'urgency' => 'routine',
            'referral_reason' => 'Integration test referral for coordinated transfer.',
            'status' => $status,
            'created_by' => $creator->id,
            'submitted_at' => $status === Referral::STATUS_DRAFT ? null : now(),
        ], $attributes));
    }
}
