<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Models\TenantUsageEvent;
use App\Models\TenantUsageLedgerRepair;
use App\Models\User;
use App\Services\UsageEventLedger\UsageEventLedgerService;
use App\Services\UsageLedgerAnomaly\UsageLedgerRepairDecision;
use App\Services\UsageLedgerAnomaly\UsageLedgerRepairPlanner;
use App\Services\UsageLedgerAnomaly\UsageLedgerRepairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Sprint 28 — governed repair is dry-run by default, requires explicit apply +
 * reason + actor, is audit-logged, appends correction records (never mutates the
 * ledger), is idempotent, and can never drive effective usage negative
 * (ULR-R007..R013).
 */
class UsageLedgerRepairTest extends TestCase
{
    use RefreshDatabase;

    private function duplicatePair(Tenant $tenant): void
    {
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
                'metadata' => ['report_type' => 'daily-sales'],
            ]);
        }
    }

    private function ledger(): UsageEventLedgerService
    {
        return app(UsageEventLedgerService::class);
    }

    public function test_repair_plan_is_dry_run_and_does_not_mutate_db(): void
    {
        $tenant = Tenant::factory()->create();
        $this->duplicatePair($tenant);

        $decisions = app(UsageLedgerRepairPlanner::class)->plan();

        $this->assertNotEmpty($decisions);
        $this->assertSame(0, TenantUsageLedgerRepair::query()->count());
    }

    public function test_repair_apply_refuses_without_apply_flag(): void
    {
        $this->artisan('usage-ledger:repair-apply')->assertExitCode(1);
    }

    public function test_repair_apply_requires_reason(): void
    {
        $this->artisan('usage-ledger:repair-apply --apply --actor=system')->assertExitCode(1);
    }

    public function test_repair_apply_requires_actor(): void
    {
        $this->artisan('usage-ledger:repair-apply --apply --reason=fix')->assertExitCode(1);
    }

    public function test_repair_apply_writes_audit_log_and_correction_record(): void
    {
        User::factory()->platformAdmin()->create();
        $tenant = Tenant::factory()->create();
        $this->duplicatePair($tenant);

        $this->artisan('usage-ledger:repair-apply --apply --reason=collapse-dupes --actor=platform-admin')
            ->assertExitCode(0);

        // Original ledger events are untouched (append-only preserved).
        $this->assertSame(2, TenantUsageEvent::query()->count());
        // A governed correction record exists.
        $repair = TenantUsageLedgerRepair::query()->first();
        $this->assertNotNull($repair);
        $this->assertSame(-1, (int) $repair->quantity_delta);
        $this->assertSame('collapse-dupes', $repair->reason);
        // An admin audit log entry was written.
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => AdminAuditLog::ACTION_USAGE_LEDGER_REPAIR_APPLIED,
            'target_type' => AdminAuditLog::TARGET_TENANT_USAGE_LEDGER_REPAIR,
        ]);
    }

    public function test_duplicate_correction_fixes_effective_usage(): void
    {
        $tenant = Tenant::factory()->create();
        $this->duplicatePair($tenant);

        $this->assertSame(2, $this->ledger()->rawMeterCount($tenant, 'reports.exports.monthly', '2026-07'));
        $this->assertSame(2, $this->ledger()->meterCount($tenant, 'reports.exports.monthly', '2026-07'));

        $this->artisan('usage-ledger:repair-apply --apply --reason=fix --actor=system')->assertExitCode(0);

        // Ledger unchanged, but effective usage corrected to 1.
        $this->assertSame(2, $this->ledger()->rawMeterCount($tenant, 'reports.exports.monthly', '2026-07'));
        $this->assertSame(1, $this->ledger()->meterCount($tenant, 'reports.exports.monthly', '2026-07'));
    }

    public function test_repair_apply_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        $this->duplicatePair($tenant);

        $this->artisan('usage-ledger:repair-apply --apply --reason=fix --actor=system')->assertExitCode(0);
        $this->artisan('usage-ledger:repair-apply --apply --reason=fix --actor=system')->assertExitCode(0);

        $this->assertSame(1, TenantUsageLedgerRepair::query()->count());
        $this->assertSame(1, $this->ledger()->meterCount($tenant, 'reports.exports.monthly', '2026-07'));
    }

    public function test_effective_usage_cannot_go_negative(): void
    {
        $tenant = Tenant::factory()->create();
        // one real event → raw usage 1
        TenantUsageEvent::query()->create([
            'tenant_id' => $tenant->id,
            'event_key' => TenantUsageEvent::EVENT_REPORT_EXPORTED,
            'event_category' => TenantUsageEvent::CATEGORY_REPORT_EXPORT,
            'meter_key' => 'reports.exports.monthly',
            'quantity' => 1,
            'occurred_at' => Carbon::create(2026, 7, 15, 10),
            'period_key' => '2026-07',
            'idempotency_key' => 'only',
            'source' => 'api',
            'request_fingerprint' => 'fp',
        ]);

        $decision = new UsageLedgerRepairDecision(
            action: UsageLedgerRepairDecision::ACTION_APPLY,
            anomalyType: 'duplicate_idempotency',
            severity: 'critical',
            tenantId: (int) $tenant->id,
            meterKey: 'reports.exports.monthly',
            periodKey: '2026-07',
            repairType: TenantUsageLedgerRepair::TYPE_DUPLICATE_USAGE_CORRECTION,
            quantityDelta: -100, // absurdly large over-correction
            repairKey: 'test:overcorrect',
            summary: 'over-correct',
        );

        app(UsageLedgerRepairService::class)->apply([$decision], 'fix', 'system', false);

        $this->assertSame(0, $this->ledger()->meterCount($tenant, 'reports.exports.monthly', '2026-07'));
        $this->assertGreaterThanOrEqual(0, $this->ledger()->meterCount($tenant, 'reports.exports.monthly', '2026-07'));
        // The stored delta was clamped to -1 (not -100).
        $this->assertSame(-1, (int) TenantUsageLedgerRepair::query()->value('quantity_delta'));
    }

    public function test_non_safe_anomalies_are_not_auto_repaired(): void
    {
        $tenant = Tenant::factory()->create();
        // unknown meter → warning, manual-review-only
        TenantUsageEvent::query()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'x.happened',
            'event_category' => 'x',
            'meter_key' => 'unknown.meter.key',
            'quantity' => 1,
            'occurred_at' => Carbon::create(2026, 7, 15, 10),
            'period_key' => '2026-07',
            'idempotency_key' => 'u1',
            'source' => 'api',
            'request_fingerprint' => 'fp',
        ]);

        $this->artisan('usage-ledger:repair-apply --apply --reason=fix --actor=system')->assertExitCode(0);

        $this->assertSame(0, TenantUsageLedgerRepair::query()->count());
    }

    public function test_repair_summary_is_redacted(): void
    {
        User::factory()->platformAdmin()->create();
        $tenant = Tenant::factory()->create();
        $this->duplicatePair($tenant);
        $this->artisan('usage-ledger:repair-apply --apply --reason=fix --actor=system')->assertExitCode(0);

        $repair = TenantUsageLedgerRepair::query()->firstOrFail();
        $this->assertArrayNotHasKey('token', (array) $repair->metadata);
        $this->assertArrayNotHasKey('secret', (array) $repair->metadata);
    }
}
