<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantUsageEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Sprint 28 — the usage-ledger commands run and the go-no-go gate passes on a
 * clean baseline (ULR-R015). anomaly-scan exits non-zero on critical anomalies.
 */
class UsageLedgerCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_anomaly_scan_runs_clean(): void
    {
        $this->artisan('usage-ledger:anomaly-scan')->assertExitCode(0);
    }

    public function test_anomaly_scan_fails_on_critical_unless_allowed(): void
    {
        $tenant = Tenant::factory()->create();
        foreach (['k1', 'k2'] as $key) {
            TenantUsageEvent::query()->create([
                'tenant_id' => $tenant->id,
                'event_key' => TenantUsageEvent::EVENT_REPORT_EXPORTED,
                'event_category' => TenantUsageEvent::CATEGORY_REPORT_EXPORT,
                'meter_key' => TenantUsageEvent::METER_REPORTS_EXPORTS_MONTHLY,
                'quantity' => 1,
                'occurred_at' => Carbon::create(2026, 7, 15, 10),
                'period_key' => '2026-07',
                'idempotency_key' => $key,
                'source' => 'api',
                'request_fingerprint' => 'dupe-fp',
            ]);
        }

        $this->artisan('usage-ledger:anomaly-scan')->assertExitCode(1);
        $this->artisan('usage-ledger:anomaly-scan --allow-critical')->assertExitCode(0);
    }

    public function test_repair_plan_and_dry_run_run(): void
    {
        $this->artisan('usage-ledger:repair-plan --reason=probe')->assertExitCode(0);
        $this->artisan('usage-ledger:repair-apply --dry-run --reason=probe --actor=system')->assertExitCode(0);
    }

    public function test_repair_summary_runs(): void
    {
        $this->artisan('usage-ledger:repair-summary')->assertExitCode(0);
    }

    public function test_go_no_go_passes_on_clean_baseline(): void
    {
        $this->artisan('usage-ledger:go-no-go')->assertExitCode(0);
    }
}
