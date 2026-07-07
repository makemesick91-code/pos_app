<?php

namespace Tests\Feature;

use App\Services\Pilot\PilotHealthSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 16 — PilotHealthSummaryService area aggregation and decision.
 */
class PilotHealthSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PilotHealthSummaryService
    {
        return app(PilotHealthSummaryService::class);
    }

    public function test_canonical_health_areas_are_present(): void
    {
        $summary = $this->service()->evaluate();

        $keys = array_column($summary['areas'], 'key');

        foreach (['app_access', 'product_sync', 'payment_qris', 'offline_sync', 'reports_closing', 'subscription_device', 'operator_feedback', 'issue_register'] as $expected) {
            $this->assertContains($expected, $keys);
        }
    }

    public function test_all_healthy_areas_produce_go(): void
    {
        $summary = $this->service()->evaluate();

        $this->assertSame(0, $summary['counts']['WARN']);
        $this->assertSame(0, $summary['counts']['FAIL']);
        $this->assertSame('GO', $summary['decision']);
    }

    public function test_warning_area_produces_watch(): void
    {
        $summary = $this->service()->evaluate(['areas' => ['payment_qris' => 'WARN']]);

        $this->assertSame(1, $summary['counts']['WARN']);
        $this->assertSame('WATCH', $summary['decision']);
    }

    public function test_failing_area_produces_no_go(): void
    {
        $summary = $this->service()->evaluate(['areas' => ['offline_sync' => 'FAIL']]);

        $this->assertSame(1, $summary['counts']['FAIL']);
        $this->assertSame('NO-GO', $summary['decision']);
    }

    public function test_result_shape_has_summary_counts_and_decision(): void
    {
        $summary = $this->service()->evaluate();

        $this->assertArrayHasKey('total_areas', $summary);
        $this->assertArrayHasKey('counts', $summary);
        $this->assertArrayHasKey('areas', $summary);
        $this->assertArrayHasKey('decision', $summary);
        $this->assertArrayHasKey('PASS', $summary['counts']);
        $this->assertArrayHasKey('WARN', $summary['counts']);
        $this->assertArrayHasKey('FAIL', $summary['counts']);
    }
}
