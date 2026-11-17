<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\BedStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsNetwork;
use Tests\TestCase;

class BedUpdateConcurrencyTest extends TestCase
{
    use BuildsNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccess();
    }

    public function test_status_update_with_current_timestamp_succeeds_and_records_history(): void
    {
        $hospital = $this->hospital();
        $manager = $this->user('bed_manager', $hospital);
        $bed = $this->bed($hospital);

        $this->actingAs($manager)
            ->patchJson("/api/v1/beds/{$bed->id}/status", [
                'status' => Bed::STATUS_OCCUPIED,
                'reason' => 'Patient admitted',
                'expected_updated_at' => $bed->updated_at->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', Bed::STATUS_OCCUPIED);

        $this->assertSame(Bed::STATUS_OCCUPIED, $bed->fresh()->status);
        $this->assertDatabaseHas('bed_status_history', [
            'bed_id' => $bed->id,
            'old_status' => Bed::STATUS_AVAILABLE,
            'new_status' => Bed::STATUS_OCCUPIED,
            'changed_by' => $manager->id,
        ]);
    }

    public function test_stale_timestamp_is_rejected_with_http_409(): void
    {
        $hospital = $this->hospital();
        $manager = $this->user('bed_manager', $hospital);
        $bed = $this->bed($hospital);
        $staleTimestamp = $bed->updated_at->copy()->subMinutes(5)->toIso8601String();

        $this->actingAs($manager)
            ->patchJson("/api/v1/beds/{$bed->id}/status", [
                'status' => Bed::STATUS_OCCUPIED,
                'expected_updated_at' => $staleTimestamp,
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertSame(Bed::STATUS_AVAILABLE, $bed->fresh()->status);
        $this->assertSame(0, BedStatusHistory::query()->where('bed_id', $bed->id)->count());
    }

    public function test_staff_cannot_update_beds_at_another_hospital(): void
    {
        $home = $this->hospital();
        $other = $this->hospital();
        $manager = $this->user('bed_manager', $home);
        $foreignBed = $this->bed($other);

        $this->actingAs($manager)
            ->patchJson("/api/v1/beds/{$foreignBed->id}/status", [
                'status' => Bed::STATUS_OCCUPIED,
            ])
            ->assertForbidden();

        $this->assertSame(Bed::STATUS_AVAILABLE, $foreignBed->fresh()->status);
    }

    public function test_bed_status_cannot_be_set_to_reserved_outside_the_referral_workflow(): void
    {
        $hospital = $this->hospital();
        $manager = $this->user('bed_manager', $hospital);
        $bed = $this->bed($hospital);

        $this->actingAs($manager)
            ->patchJson("/api/v1/beds/{$bed->id}/status", [
                'status' => Bed::STATUS_RESERVED,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(Bed::STATUS_AVAILABLE, $bed->fresh()->status);
    }

    public function test_out_of_service_requires_a_reason(): void
    {
        $hospital = $this->hospital();
        $manager = $this->user('bed_manager', $hospital);
        $bed = $this->bed($hospital);

        $this->actingAs($manager)
            ->patchJson("/api/v1/beds/{$bed->id}/status", [
                'status' => Bed::STATUS_OUT_OF_SERVICE,
            ])
            ->assertStatus(422);

        $this->actingAs($manager)
            ->patchJson("/api/v1/beds/{$bed->id}/status", [
                'status' => Bed::STATUS_OUT_OF_SERVICE,
                'reason' => 'Scheduled maintenance',
            ])
            ->assertOk();

        $this->assertSame(Bed::STATUS_OUT_OF_SERVICE, $bed->fresh()->status);
    }
}
