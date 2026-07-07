<?php

namespace Tests\Feature;

use App\Services\Pilot\OperatorUatSummaryService;
use Tests\TestCase;

/**
 * Sprint 14 — OperatorUatSummaryService scenario + issue gating.
 */
class OperatorUatSummaryServiceTest extends TestCase
{
    private function service(): OperatorUatSummaryService
    {
        return app(OperatorUatSummaryService::class);
    }

    public function test_canonical_scenarios_are_present(): void
    {
        $scenarios = $this->service()->canonicalScenarios();

        $this->assertArrayHasKey('login', $scenarios);
        $this->assertArrayHasKey('cash_sale', $scenarios);
        $this->assertArrayHasKey('qris_status', $scenarios);
        $this->assertArrayHasKey('offline_cash_sale', $scenarios);
        $this->assertArrayHasKey('daily_closing', $scenarios);
        $this->assertArrayHasKey('subscription_device', $scenarios);
        $this->assertArrayHasKey('admin_onboarding', $scenarios);
        $this->assertGreaterThanOrEqual(15, count($scenarios));
    }

    public function test_no_blocker_issues_allows_go(): void
    {
        $summary = $this->service()->evaluate([]);

        $this->assertSame(0, $summary['blocking_issues']);
        $this->assertSame('GO', $summary['decision']);
    }

    public function test_open_blocker_issue_causes_no_go(): void
    {
        $summary = $this->service()->evaluate([
            'issues' => [
                ['severity' => 'BLOCKER', 'status' => 'OPEN'],
            ],
        ]);

        $this->assertSame(1, $summary['blocking_issues']);
        $this->assertSame('NO-GO', $summary['decision']);
    }

    public function test_open_critical_issue_causes_no_go(): void
    {
        $summary = $this->service()->evaluate([
            'issues' => [
                ['severity' => 'CRITICAL', 'status' => 'IN_PROGRESS'],
            ],
        ]);

        $this->assertSame('NO-GO', $summary['decision']);
    }

    public function test_failing_scenario_causes_no_go(): void
    {
        $summary = $this->service()->evaluate([
            'scenarios' => ['cash_sale' => 'FAIL'],
        ]);

        $this->assertSame('NO-GO', $summary['decision']);
    }

    public function test_open_major_issue_causes_watch(): void
    {
        $summary = $this->service()->evaluate([
            'issues' => [
                ['severity' => 'MAJOR', 'status' => 'OPEN'],
            ],
        ]);

        $this->assertSame(1, $summary['watch_issues']);
        $this->assertSame('WATCH', $summary['decision']);
    }

    public function test_closed_blocker_issue_does_not_block(): void
    {
        $summary = $this->service()->evaluate([
            'issues' => [
                ['severity' => 'BLOCKER', 'status' => 'CLOSED'],
            ],
        ]);

        $this->assertSame(0, $summary['blocking_issues']);
        $this->assertSame('GO', $summary['decision']);
    }
}
