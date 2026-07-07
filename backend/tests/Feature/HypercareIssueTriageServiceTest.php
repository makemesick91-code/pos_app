<?php

namespace Tests\Feature;

use App\Services\Pilot\HypercareIssueTriageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 16 — HypercareIssueTriageService severity classification and decision.
 */
class HypercareIssueTriageServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): HypercareIssueTriageService
    {
        return app(HypercareIssueTriageService::class);
    }

    public function test_severity_levels_are_defined(): void
    {
        $levels = $this->service()->severityLevels();

        foreach (['BLOCKER', 'CRITICAL', 'MAJOR', 'MINOR', 'TRIVIAL'] as $level) {
            $this->assertArrayHasKey($level, $levels);
        }
    }

    public function test_no_issues_is_go(): void
    {
        $summary = $this->service()->evaluate(['issues' => []]);

        $this->assertSame('GO', $summary['decision']);
    }

    public function test_open_blocker_causes_no_go(): void
    {
        $summary = $this->service()->evaluate(['issues' => [
            ['severity' => 'BLOCKER', 'status' => 'OPEN'],
        ]]);

        $this->assertSame(1, $summary['open_blocker']);
        $this->assertSame('NO-GO', $summary['decision']);
    }

    public function test_open_critical_causes_no_go(): void
    {
        $summary = $this->service()->evaluate(['issues' => [
            ['severity' => 'CRITICAL', 'status' => 'IN_PROGRESS'],
        ]]);

        $this->assertSame(1, $summary['open_critical']);
        $this->assertSame('NO-GO', $summary['decision']);
    }

    public function test_open_major_causes_watch(): void
    {
        $summary = $this->service()->evaluate(['issues' => [
            ['severity' => 'MAJOR', 'status' => 'OPEN'],
        ]]);

        $this->assertSame(1, $summary['open_major']);
        $this->assertSame('WATCH', $summary['decision']);
    }

    public function test_open_minor_and_trivial_remain_go(): void
    {
        $summary = $this->service()->evaluate(['issues' => [
            ['severity' => 'MINOR', 'status' => 'OPEN'],
            ['severity' => 'TRIVIAL', 'status' => 'OPEN'],
        ]]);

        $this->assertSame('GO', $summary['decision']);
    }

    public function test_accepted_risk_blocker_does_not_gate(): void
    {
        $summary = $this->service()->evaluate(['issues' => [
            ['severity' => 'BLOCKER', 'status' => 'ACCEPTED_RISK'],
            ['severity' => 'CRITICAL', 'status' => 'CLOSED'],
            ['severity' => 'CRITICAL', 'status' => 'FIXED'],
        ]]);

        $this->assertSame(0, $summary['blocking_issues']);
        $this->assertSame('GO', $summary['decision']);
    }

    public function test_result_shape_has_counts_and_decision(): void
    {
        $summary = $this->service()->evaluate();

        $this->assertArrayHasKey('severity_levels', $summary);
        $this->assertArrayHasKey('open_blocker', $summary);
        $this->assertArrayHasKey('open_critical', $summary);
        $this->assertArrayHasKey('open_major', $summary);
        $this->assertArrayHasKey('blocking_issues', $summary);
        $this->assertArrayHasKey('decision', $summary);
    }
}
