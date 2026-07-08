<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 29 — the export governance commands work and the go-no-go gate passes
 * while all Sprint 25–28 prior gates stay green (EGC-R014, EGC-R015).
 */
class ExportGovernanceCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_scan_runs_and_reports_the_metered_export(): void
    {
        $this->artisan('export-governance:route-scan')
            ->assertExitCode(0);

        $this->artisan('export-governance:route-scan --strict')
            ->assertExitCode(0);
    }

    public function test_coverage_summary_runs(): void
    {
        $this->artisan('export-governance:coverage-summary')->assertExitCode(0);
    }

    public function test_metering_audit_passes_on_clean_registry(): void
    {
        $this->artisan('export-governance:metering-audit')->assertExitCode(0);
    }

    public function test_go_no_go_passes(): void
    {
        $this->artisan('export-governance:go-no-go')->assertExitCode(0);
        $this->artisan('export-governance:go-no-go --strict')->assertExitCode(0);
    }

    public function test_prior_sprint_gates_still_green(): void
    {
        $this->artisan('usage-ledger:go-no-go --strict')->assertExitCode(0);
        $this->artisan('report-export-metering:go-no-go')->assertExitCode(0);
        $this->artisan('tenant-plan:go-no-go')->assertExitCode(0);
        $this->artisan('tenant-lifecycle:go-no-go')->assertExitCode(0);
    }
}
