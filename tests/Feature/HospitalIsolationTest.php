<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\Role;
use App\Models\TransferRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsNetwork;
use Tests\TestCase;

class HospitalIsolationTest extends TestCase
{
    use BuildsNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccess();
    }

    public function test_hospital_staff_cannot_read_or_act_on_referrals_outside_their_hospital(): void
    {
        $referring = $this->hospital();
        $receiving = $this->hospital();
        $outsider = $this->hospital();

        $creator = $this->user('referral_coordinator', $referring);
        $receiver = $this->user('admission_staff', $receiving);
        $stranger = $this->user('referral_coordinator', $outsider);

        $referral = $this->referral($referring, $receiving, $creator, Referral::STATUS_SUBMITTED);

        $this->actingAs($stranger)->get("/referrals/{$referral->id}")->assertForbidden();
        $this->actingAs($stranger)->get("/api/v1/referrals/{$referral->id}")->assertForbidden();
        $this->actingAs($stranger)->postJson("/api/v1/referrals/{$referral->id}/start-review")->assertForbidden();
        $this->actingAs($stranger)->post("/referrals/{$referral->id}/accept")->assertForbidden();

        $this->assertSame(Referral::STATUS_SUBMITTED, $referral->fresh()->status);

        // Neither participant sees this referral leak to the outsider's lists.
        $this->actingAs($stranger)
            ->get('/referrals')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Referrals/Index')
                ->has('referrals.data', 0));

        $this->actingAs($stranger)
            ->getJson('/api/v1/referrals')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertTrue($receiver->hasRole('admission_staff'));
    }

    public function test_network_administrator_without_membership_cannot_read_patient_linked_records(): void
    {
        $referring = $this->hospital();
        $receiving = $this->hospital();
        $creator = $this->user('referral_coordinator', $referring);
        $admin = $this->user('national_network_admin');

        $referral = $this->referral($referring, $receiving, $creator, Referral::STATUS_SUBMITTED);
        TransferRecord::query()->create([
            'referral_id' => $referral->id,
            'status' => TransferRecord::STATUS_SCHEDULED,
            'transport_type' => 'ambulance',
            'created_by' => $creator->id,
        ]);

        $this->actingAs($admin)->get('/referrals')->assertForbidden();
        $this->actingAs($admin)->get('/transfers')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/v1/referrals')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/v1/transfers')->assertForbidden();

        // Aggregate reporting remains available to the network administrator.
        $this->actingAs($admin)->get('/reports')->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/reports/overview')->assertOk();
    }

    public function test_network_administrator_membership_does_not_expand_patient_linked_scope(): void
    {
        $home = $this->hospital();
        $otherReferrer = $this->hospital();
        $otherReceiver = $this->hospital();

        $admin = $this->user('national_network_admin');
        $this->attachMembership($admin, $home, Role::query()->where('slug', 'hospital_admin')->firstOrFail());

        $homeCreator = $this->user('referral_coordinator', $home);
        $outsideCreator = $this->user('referral_coordinator', $otherReferrer);

        $visible = $this->referral($home, $otherReceiver, $homeCreator, Referral::STATUS_SUBMITTED);
        $hidden = $this->referral($otherReferrer, $otherReceiver, $outsideCreator, Referral::STATUS_SUBMITTED);

        TransferRecord::query()->create([
            'referral_id' => $hidden->id,
            'status' => TransferRecord::STATUS_SCHEDULED,
            'transport_type' => 'ambulance',
            'created_by' => $outsideCreator->id,
        ]);

        $this->actingAs($admin->fresh())
            ->get('/referrals')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Referrals/Index')
                ->has('referrals.data', 1)
                ->where('referrals.data.0.id', $visible->id));

        $this->actingAs($admin->fresh())
            ->get('/transfers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transfers/Index')
                ->has('transfers.data', 0));

        $this->actingAs($admin->fresh())
            ->getJson('/api/v1/referrals')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);

        $this->actingAs($admin->fresh())
            ->getJson('/api/v1/transfers')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_hospital_staff_cannot_read_individual_beds_outside_their_membership(): void
    {
        $home = $this->hospital();
        $other = $this->hospital();

        $bedManager = $this->user('bed_manager', $home);
        $foreignBed = $this->bed($other);

        $this->actingAs($bedManager)
            ->getJson("/api/v1/beds/{$foreignBed->id}")
            ->assertForbidden();

        $this->actingAs($bedManager)
            ->getJson('/api/v1/beds?hospital_id='.$other->id)
            ->assertForbidden();

        $ownBed = $this->bed($home);

        $this->actingAs($bedManager)
            ->getJson("/api/v1/beds/{$ownBed->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ownBed->id);
    }

    public function test_transfer_board_only_lists_transfers_for_the_users_hospitals(): void
    {
        $home = $this->hospital();
        $partner = $this->hospital();
        $otherA = $this->hospital();
        $otherB = $this->hospital();

        $homeUser = $this->user('referral_coordinator', $home);
        $otherUser = $this->user('referral_coordinator', $otherA);

        $visibleReferral = $this->referral($home, $partner, $homeUser, Referral::STATUS_TRANSFER_SCHEDULED);
        $hiddenReferral = $this->referral($otherA, $otherB, $otherUser, Referral::STATUS_TRANSFER_SCHEDULED);

        $visible = TransferRecord::query()->create([
            'referral_id' => $visibleReferral->id,
            'status' => TransferRecord::STATUS_SCHEDULED,
            'transport_type' => 'ambulance',
            'created_by' => $homeUser->id,
        ]);

        TransferRecord::query()->create([
            'referral_id' => $hiddenReferral->id,
            'status' => TransferRecord::STATUS_SCHEDULED,
            'transport_type' => 'ambulance',
            'created_by' => $otherUser->id,
        ]);

        $this->actingAs($homeUser)
            ->get('/transfers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Transfers/Index')
                ->has('transfers.data', 1)
                ->where('transfers.data.0.id', $visible->id));

        $this->actingAs($homeUser)
            ->getJson("/api/v1/transfers/{$hiddenReferral->transfer->id}")
            ->assertForbidden();
    }
}
