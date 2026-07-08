<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Sprint 27 — the usage event ledger / report export metering commands run, gate
 * correctly, and the Sprint 24/25/26 gates still pass (UEL-R014, UEL-R015).
 */
class UsageEventLedgerCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_passes(): void
    {
        $this->artisan('usage-event-ledger:readiness')->assertExitCode(0);
    }

    public function test_ledger_summary_runs(): void
    {
        $this->artisan('usage-event-ledger:summary')->assertExitCode(0);
    }

    public function test_report_export_metering_summary_runs(): void
    {
        $this->artisan('report-export-metering:summary')->assertExitCode(0);
    }

    public function test_enforcement_audit_passes(): void
    {
        $this->artisan('report-export-metering:enforcement-audit')->assertExitCode(0);
    }

    public function test_go_no_go_passes(): void
    {
        $this->artisan('report-export-metering:go-no-go')->assertExitCode(0);
    }

    public function test_enforcement_audit_fails_when_meter_deferred(): void
    {
        // Simulate regressing the meter back to deferred — the audit must FAIL.
        $limits = (array) config('tenant_plan.usage_limits');
        $limits['reports.exports.monthly']['meterable'] = false;
        Config::set('tenant_plan.usage_limits', $limits);

        $this->artisan('report-export-metering:enforcement-audit')->assertExitCode(1);
    }

    public function test_readiness_fails_when_guardrail_enabled(): void
    {
        Config::set('usage_event_ledger.failed_export_counts_usage_allowed', true);

        $this->artisan('usage-event-ledger:readiness')->assertExitCode(1);
    }

    public function test_prior_sprint_gates_still_pass(): void
    {
        $this->artisan('tenant-plan:go-no-go')->assertExitCode(0);
        $this->artisan('tenant-lifecycle:go-no-go')->assertExitCode(0);
    }
}
