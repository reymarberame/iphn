<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsNetwork;
use Tests\TestCase;

class HospitalProfileTest extends TestCase
{
    use BuildsNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccess();
    }

    public function test_hospital_admin_can_update_their_own_facility_profile(): void
    {
        $hospital = $this->hospital([
            'hospital_code' => 'OWN-1001',
            'ownership_type' => 'government',
            'status' => 'operational',
            'facility_type' => 'secondary',
        ]);
        $admin = $this->user('hospital_admin', $hospital);

        $otherMunicipality = Municipality::query()->create([
            'province_id' => $this->municipality()->province_id,
            'code' => 'M-OTHER',
            'name' => 'Other Municipality',
            'type' => 'municipality',
        ]);

        $service = Service::query()->create([
            'name' => 'Internal Medicine',
            'slug' => 'internal-medicine',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get("/hospitals/{$hospital->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Hospitals/Show')
                ->where('canManageProfile', true)
                ->where('isNetworkUser', false)
                ->has('municipalities')
                ->has('services'));

        $this->actingAs($admin)
            ->patch("/hospitals/{$hospital->id}/profile", [
                'name' => 'Updated Facility Name',
                'short_name' => 'UPD',
                'facility_type' => 'tertiary',
                'address' => 'Updated Address',
                'phone' => '+63 900 000 0000',
                'email' => 'profile@hospital.test',
                'bed_capacity' => 250,
                'notes' => 'Updated operational notes.',
                'services' => [$service->id],

                // Restricted fields: these must be ignored for hospital-scoped users.
                'hospital_code' => 'HACK-9999',
                'ownership_type' => 'private',
                'status' => 'inactive',
                'municipality_id' => $otherMunicipality->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $hospital->refresh();

        $this->assertSame('Updated Facility Name', $hospital->name);
        $this->assertSame('UPD', $hospital->short_name);
        $this->assertSame('tertiary', $hospital->facility_type);
        $this->assertSame('+63 900 000 0000', $hospital->phone);
        $this->assertSame(250, $hospital->bed_capacity);
        $this->assertNotNull($hospital->profile_updated_at);

        // Network-controlled fields are unchanged.
        $this->assertSame('OWN-1001', $hospital->hospital_code);
        $this->assertSame('government', $hospital->ownership_type);
        $this->assertSame('operational', $hospital->status);
        $this->assertSame($this->municipality()->id, $hospital->municipality_id);

        $this->assertTrue($hospital->services()->whereKey($service->id)->exists());

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'hospital_id' => $hospital->id,
            'action' => 'hospital.profile.updated',
            'subject_id' => $hospital->id,
        ]);
    }

    public function test_hospital_admin_cannot_update_another_facility(): void
    {
        $home = $this->hospital();
        $other = $this->hospital(['name' => 'Other Hospital']);
        $admin = $this->user('hospital_admin', $home);

        $this->actingAs($admin)
            ->get("/hospitals/{$other->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Hospitals/Show')
                ->where('canManageProfile', false));

        $this->actingAs($admin)
            ->patch("/hospitals/{$other->id}/profile", ['name' => 'Hijacked Hospital'])
            ->assertForbidden();

        $this->assertSame('Other Hospital', $other->fresh()->name);
    }

    public function test_hospital_staff_cannot_update_the_facility_profile(): void
    {
        $hospital = $this->hospital(['name' => 'Staff Hospital']);
        $staff = $this->user('hospital_staff', $hospital);

        $this->actingAs($staff)
            ->get("/hospitals/{$hospital->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Hospitals/Show')
                ->where('canManageProfile', false));

        $this->actingAs($staff)
            ->patch("/hospitals/{$hospital->id}/profile", ['name' => 'Staff Renamed Hospital'])
            ->assertForbidden();

        $this->assertSame('Staff Hospital', $hospital->fresh()->name);
    }

    public function test_network_administrator_can_update_network_controlled_fields(): void
    {
        $hospital = $this->hospital([
            'hospital_code' => 'NET-1001',
            'ownership_type' => 'government',
            'status' => 'operational',
        ]);
        $admin = $this->user('super_admin');

        $otherMunicipality = Municipality::query()->create([
            'province_id' => $this->municipality()->province_id,
            'code' => 'M-NET',
            'name' => 'Network Municipality',
            'type' => 'municipality',
        ]);

        $this->actingAs($admin)
            ->get("/hospitals/{$hospital->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Hospitals/Show')
                ->where('canManageProfile', true)
                ->where('isNetworkUser', true));

        $this->actingAs($admin)
            ->patch("/hospitals/{$hospital->id}/profile", [
                'hospital_code' => 'NET-2002',
                'name' => 'Network Updated Hospital',
                'ownership_type' => 'private',
                'status' => 'limited',
                'municipality_id' => $otherMunicipality->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $hospital->refresh();

        $this->assertSame('NET-2002', $hospital->hospital_code);
        $this->assertSame('Network Updated Hospital', $hospital->name);
        $this->assertSame('private', $hospital->ownership_type);
        $this->assertSame('limited', $hospital->status);
        $this->assertSame($otherMunicipality->id, $hospital->municipality_id);
    }
}
