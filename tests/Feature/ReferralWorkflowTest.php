<?php

namespace Tests\Feature;

use App\Models\Referral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsNetwork;
use Tests\TestCase;

class ReferralWorkflowTest extends TestCase
{
    use BuildsNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccess();
    }

    public function test_transition_matrix_blocks_invalid_status_jumps(): void
    {
        $referring = $this->hospital();
        $receiving = $this->hospital();
        $creator = $this->user('hospital_staff', $referring);
        $referral = $this->referral($referring, $receiving, $creator);

        $this->assertTrue($referral->canTransitionTo(Referral::STATUS_SUBMITTED));
        $this->assertTrue($referral->canTransitionTo(Referral::STATUS_CANCELLED));
        $this->assertFalse($referral->canTransitionTo(Referral::STATUS_ACCEPTED));
        $this->assertFalse($referral->canTransitionTo(Referral::STATUS_COMPLETED));

        $referral->forceFill(['status' => Referral::STATUS_IN_TRANSIT])->save();
        $this->assertTrue($referral->canTransitionTo(Referral::STATUS_ARRIVED));
        $this->assertFalse($referral->canTransitionTo(Referral::STATUS_COMPLETED));

        $referral->forceFill(['status' => Referral::STATUS_COMPLETED])->save();
        $this->assertTrue($referral->isTerminal());
        $this->assertFalse($referral->canTransitionTo(Referral::STATUS_DRAFT));
    }

    public function test_receiving_staff_can_accept_a_submitted_referral(): void
    {
        $referring = $this->hospital();
        $receiving = $this->hospital();
        $creator = $this->user('hospital_staff', $referring);
        $receiver = $this->user('hospital_staff', $receiving);
        $referral = $this->referral($referring, $receiving, $creator, Referral::STATUS_SUBMITTED);

        $this->actingAs($receiver)
            ->post("/referrals/{$referral->id}/accept", ['response_note' => 'Accepted for admission'])
            ->assertRedirect();

        $referral->refresh();

        $this->assertSame(Referral::STATUS_ACCEPTED, $referral->status);
        $this->assertSame($receiver->id, $referral->coordinator_id);
        $this->assertNotNull($referral->accepted_at);
    }

    public function test_referring_hospital_cannot_accept_its_own_referral(): void
    {
        $referring = $this->hospital();
        $receiving = $this->hospital();
        $creator = $this->user('hospital_staff', $referring);
        $this->user('hospital_staff', $receiving);
        $referral = $this->referral($referring, $receiving, $creator, Referral::STATUS_SUBMITTED);

        $this->actingAs($creator)
            ->post("/referrals/{$referral->id}/accept")
            ->assertForbidden();

        $this->assertSame(Referral::STATUS_SUBMITTED, $referral->fresh()->status);
    }

    public function test_accepting_a_draft_is_rejected_by_the_transition_guard(): void
    {
        $referring = $this->hospital();
        $receiving = $this->hospital();
        $creator = $this->user('hospital_staff', $referring);
        $receiver = $this->user('hospital_staff', $receiving);
        $referral = $this->referral($referring, $receiving, $creator);

        $this->actingAs($receiver)
            ->post("/referrals/{$referral->id}/accept")
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertSame(Referral::STATUS_DRAFT, $referral->fresh()->status);
    }

    public function test_declining_requires_a_reason_and_closes_the_referral(): void
    {
        $referring = $this->hospital();
        $receiving = $this->hospital();
        $creator = $this->user('hospital_staff', $referring);
        $receiver = $this->user('hospital_staff', $receiving);
        $referral = $this->referral($referring, $receiving, $creator, Referral::STATUS_SUBMITTED);

        $this->actingAs($receiver)
            ->post("/referrals/{$referral->id}/decline", ['decline_reason' => ''])
            ->assertSessionHasErrors('decline_reason');

        $this->actingAs($receiver)
            ->post("/referrals/{$referral->id}/decline", ['decline_reason' => 'No appropriate bed available'])
            ->assertRedirect();

        $referral->refresh();

        $this->assertSame(Referral::STATUS_DECLINED, $referral->status);
        $this->assertSame('No appropriate bed available', $referral->decline_reason);
        $this->assertNotNull($referral->closed_at);
    }

    public function test_referral_detail_is_visible_to_both_participating_hospitals(): void
    {
        $referring = $this->hospital();
        $receiving = $this->hospital();
        $creator = $this->user('hospital_staff', $referring);
        $receiver = $this->user('hospital_staff', $receiving);
        $referral = $this->referral($referring, $receiving, $creator, Referral::STATUS_SUBMITTED);

        $this->actingAs($creator)
            ->get("/referrals/{$referral->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Referrals/Show')
                ->where('referral.id', $referral->id)
                ->where('abilities.is_referring', true)
                ->where('abilities.can_accept', false));

        $this->actingAs($receiver)
            ->get("/referrals/{$referral->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Referrals/Show')
                ->where('abilities.is_receiving', true)
                ->where('abilities.can_accept', true));

        // Simply viewing availability must not imply acceptance.
        $this->assertSame(Referral::STATUS_SUBMITTED, $referral->fresh()->status);
    }
}
