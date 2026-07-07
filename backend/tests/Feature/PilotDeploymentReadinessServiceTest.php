<?php

namespace Tests\Feature;

use App\Services\Pilot\PilotDeploymentReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Sprint 15 — PilotDeploymentReadinessService GO / WATCH / NO-GO aggregation.
 */
class PilotDeploymentReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PilotDeploymentReadinessService
    {
        return app(PilotDeploymentReadinessService::class);
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
        File::ensureDirectoryExists(storage_path('app'));
        File::ensureDirectoryExists(storage_path('logs'));
        config(['app.debug' => false]);

        $report = $this->service()->evaluate();

        $this->assertArrayHasKey('decision', $report);
        $this->assertArrayHasKey('checks', $report);
        $this->assertSame('PASS', $this->check($report, 'pilot.deployment_docs')['status']);
        $this->assertSame('PASS', $this->check($report, 'pilot.field_trial')['status']);
        $this->assertSame('GO', $report['decision']);
    }

    public function test_missing_deployment_checklist_causes_no_go(): void
    {
        config(['pilot_deployment.required_docs' => ['docs/pilot/pilot-deployment-checklist-MISSING.md']]);

        $report = $this->service()->evaluate();

        $this->assertSame('FAIL', $this->check($report, 'pilot.deployment_docs')['status']);
        $this->assertSame('NO-GO', $report['decision']);
    }

    public function test_missing_rollback_checklist_causes_no_go(): void
    {
        config(['pilot_deployment.required_docs' => ['docs/pilot/pilot-rollback-checklist-MISSING.md']]);

        $report = $this->service()->evaluate();

        $this->assertSame('FAIL', $this->check($report, 'pilot.deployment_docs')['status']);
        $this->assertSame('NO-GO', $report['decision']);
    }

    public function test_missing_field_issue_register_causes_no_go(): void
    {
        config(['pilot_deployment.required_docs' => ['docs/pilot/field-issue-register-MISSING.md']]);

        $report = $this->service()->evaluate();

        $this->assertSame('FAIL', $this->check($report, 'pilot.deployment_docs')['status']);
        $this->assertSame('NO-GO', $report['decision']);
    }

    public function test_missing_android_readiness_script_causes_no_go(): void
    {
        config(['pilot_deployment.android_release_readiness_script' => 'scripts/does-not-exist.sh']);

        $report = $this->service()->evaluate();

        $this->assertSame('FAIL', $this->check($report, 'pilot.android_release_readiness')['status']);
        $this->assertSame('NO-GO', $report['decision']);
    }

    public function test_warning_condition_causes_watch(): void
    {
        // Debug on in a non-production env is a WARN (release gate WATCH), not FAIL.
        config(['app.env' => 'testing', 'app.debug' => true]);

        $report = $this->service()->evaluate();

        $this->assertSame('WATCH', $report['decision']);
    }

    public function test_result_shape_has_decision_and_checks(): void
    {
        $report = $this->service()->evaluate();

        $this->assertArrayHasKey('decision', $report);
        $this->assertArrayHasKey('checks', $report);
        $this->assertArrayHasKey('field_summary', $report);
        $this->assertNotEmpty($report['checks']);
        foreach ($report['checks'] as $check) {
            $this->assertArrayHasKey('key', $check);
            $this->assertArrayHasKey('status', $check);
            $this->assertArrayHasKey('message', $check);
        }
    }
}
