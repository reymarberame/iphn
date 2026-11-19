<?php

namespace Tests\Feature;

use App\Models\Referral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsNetwork;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use BuildsNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccess();
    }

    public function test_referral_csv_neutralises_formula_injection(): void
    {
        $referring = $this->hospital();
        $receiving = $this->hospital();
        $admin = $this->user('hospital_admin', $referring);

        $this->referral($referring, $receiving, $admin, Referral::STATUS_SUBMITTED, [
            'patient_reference' => '=HYPERLINK("http://evil.test")',
        ]);

        $response = $this->actingAs($admin)->get('/reports/export/referrals');

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString("\n=HYPERLINK", $csv);
    }
}
