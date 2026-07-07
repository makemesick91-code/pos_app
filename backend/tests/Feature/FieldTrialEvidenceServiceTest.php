<?php

namespace Tests\Feature;

use App\Services\Pilot\FieldTrialEvidenceService;
use Tests\TestCase;

/**
 * Sprint 15 — FieldTrialEvidenceService GO / WATCH / NO-GO gating.
 */
class FieldTrialEvidenceServiceTest extends TestCase
{
    private function service(): FieldTrialEvidenceService
    {
        return app(FieldTrialEvidenceService::class);
    }

    public function test_canonical_evidence_categories_are_present(): void
    {
        $summary = $this->service()->evaluate();

        $this->assertGreaterThanOrEqual(13, $summary['total_categories']);
        $keys = array_column($summary['categories'], 'key');
        foreach ([
            'backend_deployment_dry_run',
            'android_rc_artifact',
            'operator_device_readiness',
            'post_deploy_smoke',
            'rollback_readiness',
            'daily_monitoring',
            'field_issue_register',
        ] as $required) {
            $this->assertContains($required, $keys);
        }
    }

    public function test_no_issues_allows_go(): void
    {
        $summary = $this->service()->evaluate(['issues' => []]);

        $this->assertSame(0, $summary['blocking_issues']);
        $this->assertSame('GO', $summary['decision']);
    }

    public function test_blocker_open_issue_causes_no_go(): void
    {
        $summary = $this->service()->evaluate([
            'issues' => [['severity' => 'BLOCKER', 'status' => 'OPEN']],
        ]);

        $this->assertSame(1, $summary['blocking_issues']);
        $this->assertSame('NO-GO', $summary['decision']);
    }

    public function test_critical_open_issue_causes_no_go(): void
    {
        $summary = $this->service()->evaluate([
            'issues' => [['severity' => 'CRITICAL', 'status' => 'IN_PROGRESS']],
        ]);

        $this->assertSame('NO-GO', $summary['decision']);
    }

    public function test_major_open_issue_produces_watch(): void
    {
        $summary = $this->service()->evaluate([
            'issues' => [['severity' => 'MAJOR', 'status' => 'OPEN']],
        ]);

        $this->assertSame(1, $summary['watch_issues']);
        $this->assertSame('WATCH', $summary['decision']);
    }

    public function test_closed_blocker_does_not_force_no_go(): void
    {
        $summary = $this->service()->evaluate([
            'issues' => [['severity' => 'BLOCKER', 'status' => 'CLOSED']],
        ]);

        $this->assertSame(0, $summary['blocking_issues']);
        $this->assertSame('GO', $summary['decision']);
    }

    public function test_result_shape(): void
    {
        $summary = $this->service()->evaluate();

        foreach (['total_categories', 'required_categories', 'blocking_issues', 'watch_issues', 'categories', 'decision'] as $key) {
            $this->assertArrayHasKey($key, $summary);
        }
    }
}
