<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\BedStatusHistory;
use App\Models\SystemSetting;
use App\Services\BedAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsNetwork;
use Tests\TestCase;

class BedAvailabilityServiceTest extends TestCase
{
    use BuildsNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccess();

        SystemSetting::put('freshness_fresh_minutes', '15');
        SystemSetting::put('freshness_aging_minutes', '60');
    }

    public function test_freshness_classification_uses_configured_thresholds(): void
    {
        $service = app(BedAvailabilityService::class);

        $this->assertSame('unknown', $service->freshness(null));
        $this->assertSame('fresh', $service->freshness(now()->subMinutes(15)));
        $this->assertSame('aging', $service->freshness(now()->subMinutes(16)));
        $this->assertSame('aging', $service->freshness(now()->subMinutes(60)));
        $this->assertSame('stale', $service->freshness(now()->subMinutes(61)));
    }

    public function test_freshness_thresholds_are_configurable(): void
    {
        SystemSetting::put('freshness_fresh_minutes', '5');
        SystemSetting::put('freshness_aging_minutes', '20');

        $service = app(BedAvailabilityService::class);

        $this->assertSame('fresh', $service->freshness(now()->subMinutes(5)));
        $this->assertSame('aging', $service->freshness(now()->subMinutes(6)));
        $this->assertSame('stale', $service->freshness(now()->subMinutes(21)));
    }

    public function test_status_counts_are_derived_from_individual_bed_statuses(): void
    {
        $hospital = $this->hospital();

        foreach ([
            Bed::STATUS_AVAILABLE,
            Bed::STATUS_AVAILABLE,
            Bed::STATUS_AVAILABLE,
            Bed::STATUS_OCCUPIED,
            Bed::STATUS_RESERVED,
            Bed::STATUS_CLEANING,
            Bed::STATUS_OUT_OF_SERVICE,
        ] as $status) {
            $this->bed($hospital, ['status' => $status]);
        }

        $counts = app(BedAvailabilityService::class)->statusCounts($hospital->id);

        $this->assertSame(7, $counts['total']);
        $this->assertSame(3, $counts['available']);
        $this->assertSame(1, $counts['occupied']);
        $this->assertSame(1, $counts['reserved']);
        $this->assertSame(1, $counts['cleaning']);
        $this->assertSame(1, $counts['out_of_service']);
        $this->assertSame(6, $counts['operational']);
    }

    public function test_hospital_summary_freshness_follows_the_latest_status_report(): void
    {
        $hospital = $this->hospital();
        $bed = $this->bed($hospital);
        $reporter = $this->user('bed_manager', $hospital);

        BedStatusHistory::query()->create([
            'bed_id' => $bed->id,
            'hospital_id' => $hospital->id,
            'old_status' => Bed::STATUS_CLEANING,
            'new_status' => Bed::STATUS_AVAILABLE,
            'reason' => 'Old report',
            'changed_by' => $reporter->id,
            'changed_at' => now()->subMinutes(90),
        ]);

        $service = app(BedAvailabilityService::class);

        $this->assertSame('stale', $service->hospitalSummary($hospital->id)['freshness']);

        BedStatusHistory::query()->create([
            'bed_id' => $bed->id,
            'hospital_id' => $hospital->id,
            'old_status' => Bed::STATUS_AVAILABLE,
            'new_status' => Bed::STATUS_OCCUPIED,
            'reason' => 'Fresh report',
            'changed_by' => $reporter->id,
            'changed_at' => now()->subMinutes(4),
        ]);

        $this->assertSame('fresh', $service->hospitalSummary($hospital->id)['freshness']);
    }
}
