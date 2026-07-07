<?php

namespace Tests\Feature;

use App\Services\Pilot\PilotMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 16 — PilotMonitoringService GO / WATCH / NO-GO aggregation.
 */
class PilotMonitoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PilotMonitoringService
    {
        return app(PilotMonitoringService::class);
    }

    private function signal(array $report, string $key): array
    {
        foreach ($report['signals'] as $signal) {
            if ($signal['key'] === $key) {
                return $signal;
            }
        }

        $this->fail("Signal '{$key}' not found in report.");
    }

    public function test_go_when_all_required_checks_pass(): void
    {
        $report = $this->service()->evaluate();

        $this->assertSame('PASS', $this->signal($report, 'monitoring_docs')['status']);
        $this->assertSame('PASS', $this->signal($report, 'release_pilot_commands')['status']);
        $this->assertSame('PASS', $this->signal($report, 'backend_health')['status']);
        $this->assertSame('GO', $report['decision']);
    }

    public function test_missing_daily_monitoring_runbook_causes_no_go(): void
    {
        config(['pilot_monitoring.required_docs' => ['docs/pilot/daily-monitoring-runbook-MISSING.md']]);

        $report = $this->service()->evaluate();

        $this->assertSame('FAIL', $this->signal($report, 'monitoring_docs')['status']);
        $this->assertSame('NO-GO', $report['decision']);
    }

    public function test_missing_hypercare_triage_workflow_causes_no_go(): void
    {
        config(['pilot_monitoring.required_docs' => ['docs/pilot/hypercare-issue-triage-workflow-MISSING.md']]);

        $report = $this->service()->evaluate();

        $this->assertSame('NO-GO', $report['decision']);
    }

    public function test_missing_severity_sla_doc_causes_no_go(): void
    {
        config(['pilot_monitoring.required_docs' => ['docs/pilot/field-issue-severity-sla-MISSING.md']]);

        $report = $this->service()->evaluate();

        $this->assertSame('NO-GO', $report['decision']);
    }

    public function test_missing_android_readiness_script_causes_no_go(): void
    {
        config(['pilot_monitoring.android_release_readiness_script' => 'scripts/does-not-exist.sh']);

        $report = $this->service()->evaluate();

        $this->assertSame('FAIL', $this->signal($report, 'android_release_readiness')['status']);
        $this->assertSame('NO-GO', $report['decision']);
    }

    public function test_non_critical_warning_produces_watch(): void
    {
        $report = $this->service()->evaluate(['signals' => ['receipt_printer' => 'WARN']]);

        $this->assertSame('WARN', $this->signal($report, 'receipt_printer')['status']);
        $this->assertSame('WATCH', $report['decision']);
    }

    public function test_critical_signal_fail_produces_no_go(): void
    {
        $report = $this->service()->evaluate(['signals' => ['qris_payment_status' => 'FAIL']]);

        $this->assertSame('FAIL', $this->signal($report, 'qris_payment_status')['status']);
        $this->assertSame('NO-GO', $report['decision']);
    }

    public function test_result_shape_has_decision_and_signals(): void
    {
        $report = $this->service()->evaluate();

        $this->assertArrayHasKey('decision', $report);
        $this->assertArrayHasKey('signals', $report);
        $this->assertNotEmpty($report['signals']);
        foreach ($report['signals'] as $signal) {
            $this->assertArrayHasKey('key', $signal);
            $this->assertArrayHasKey('status', $signal);
            $this->assertArrayHasKey('message', $signal);
            $this->assertArrayHasKey('blocking', $signal);
        }
    }
}
