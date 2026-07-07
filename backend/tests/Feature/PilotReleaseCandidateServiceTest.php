<?php

namespace Tests\Feature;

use App\Services\Pilot\PilotReleaseCandidateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Sprint 14 — PilotReleaseCandidateService GO / WATCH / NO-GO aggregation.
 */
class PilotReleaseCandidateServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PilotReleaseCandidateService
    {
        return app(PilotReleaseCandidateService::class);
    }

    private function check(array $report, string $key): array
    {
        foreach ($report['checks'] as $check) {
            if ($check['key'] === $key) {
                return $check;
            }
        }

        $this->fail("Check '{$key}' not found in report.");
    }

    public function test_go_when_all_required_checks_pass(): void
    {
        // Clean production-like readiness (debug off) with all contracts intact.
        File::ensureDirectoryExists(storage_path('app'));
        File::ensureDirectoryExists(storage_path('logs'));
        config(['app.debug' => false]);

        $report = $this->service()->evaluate();

        $this->assertArrayHasKey('decision', $report);
        $this->assertArrayHasKey('checks', $report);
        $this->assertSame('PASS', $this->check($report, 'pilot.rc_docs')['status']);
        $this->assertSame('PASS', $this->check($report, 'pilot.operator_uat')['status']);
        $this->assertSame('GO', $report['decision']);
    }

    public function test_missing_uat_checklist_causes_no_go(): void
    {
        config(['pilot_uat.required_docs' => ['docs/pilot/operator-uat-checklist-MISSING.md']]);

        $report = $this->service()->evaluate();

        $this->assertSame('FAIL', $this->check($report, 'pilot.rc_docs')['status']);
        $this->assertSame('NO-GO', $report['decision']);
    }

    public function test_missing_issue_register_causes_no_go(): void
    {
        config(['pilot_uat.required_docs' => ['docs/pilot/issue-register-MISSING.md']]);

        $report = $this->service()->evaluate();

        $this->assertSame('FAIL', $this->check($report, 'pilot.rc_docs')['status']);
        $this->assertSame('NO-GO', $report['decision']);
    }

    public function test_warning_condition_causes_watch(): void
    {
        // Debug on in a non-production env is a WARN (release gate WATCH), not a FAIL.
        config(['app.env' => 'testing', 'app.debug' => true]);

        $report = $this->service()->evaluate();

        $this->assertSame('WATCH', $report['decision']);
    }

    public function test_result_shape_has_decision_and_checks(): void
    {
        $report = $this->service()->evaluate();

        $this->assertArrayHasKey('decision', $report);
        $this->assertArrayHasKey('checks', $report);
        $this->assertArrayHasKey('uat_summary', $report);
        $this->assertNotEmpty($report['checks']);
        foreach ($report['checks'] as $check) {
            $this->assertArrayHasKey('key', $check);
            $this->assertArrayHasKey('status', $check);
            $this->assertArrayHasKey('message', $check);
        }
    }
}
