<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantUsageEvent;
use App\Services\UsageEventLedger\UsageEventLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Sprint 27 — the append-only usage event ledger: recording, redaction,
 * idempotency, stable period keys, tenant-scoped summaries (UEL-R001..R005/R013).
 */
class UsageEventLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function ledger(): UsageEventLedgerService
    {
        return app(UsageEventLedgerService::class);
    }

    public function test_can_record_usage_event_for_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $decision = $this->ledger()->append(
            tenant: $tenant,
            eventKey: TenantUsageEvent::EVENT_REPORT_EXPORTED,
            eventCategory: TenantUsageEvent::CATEGORY_REPORT_EXPORT,
            meterKey: TenantUsageEvent::METER_REPORTS_EXPORTS_MONTHLY,
            idempotencyKey: 'k-1',
        );

        $this->assertTrue($decision->recorded);
        $this->assertFalse($decision->duplicate);
        $this->assertDatabaseCount('tenant_usage_events', 1);
    }

    public function test_metadata_is_sanitized(): void
    {
        $tenant = Tenant::factory()->create();

        $event = $this->ledger()->append(
            tenant: $tenant,
            eventKey: TenantUsageEvent::EVENT_REPORT_EXPORTED,
            eventCategory: TenantUsageEvent::CATEGORY_REPORT_EXPORT,
            meterKey: TenantUsageEvent::METER_REPORTS_EXPORTS_MONTHLY,
            idempotencyKey: 'k-secret',
            metadata: ['report_type' => 'daily-sales', 'api_token' => 'sk_live_123', 'note' => 'password: hunter2'],
        )->event;

        $this->assertSame('[REDACTED]', $event->metadata['api_token']);
        $this->assertStringContainsString('[REDACTED]', $event->metadata['note']);
        $this->assertStringNotContainsString('hunter2', json_encode($event->metadata));
        $this->assertSame('daily-sales', $event->metadata['report_type']);
    }

    public function test_idempotency_key_prevents_duplicate_count(): void
    {
        $tenant = Tenant::factory()->create();

        $first = $this->ledger()->append($tenant, TenantUsageEvent::EVENT_REPORT_EXPORTED, TenantUsageEvent::CATEGORY_REPORT_EXPORT, TenantUsageEvent::METER_REPORTS_EXPORTS_MONTHLY, 'dupe');
        $second = $this->ledger()->append($tenant, TenantUsageEvent::EVENT_REPORT_EXPORTED, TenantUsageEvent::CATEGORY_REPORT_EXPORT, TenantUsageEvent::METER_REPORTS_EXPORTS_MONTHLY, 'dupe');

        $this->assertTrue($first->recorded);
        $this->assertFalse($second->recorded);
        $this->assertTrue($second->duplicate);
        $this->assertDatabaseCount('tenant_usage_events', 1);
        $this->assertSame(1, $this->ledger()->monthlyMeterCount($tenant, TenantUsageEvent::METER_REPORTS_EXPORTS_MONTHLY));
    }

    public function test_monthly_period_key_is_stable(): void
    {
        $tenant = Tenant::factory()->create();
        Carbon::setTestNow(Carbon::parse('2026-07-08 10:00:00'));

        $a = $this->ledger()->append($tenant, 'report.exported', 'report_export', 'reports.exports.monthly', 'p-a')->event;
        Carbon::setTestNow(Carbon::parse('2026-07-28 23:59:00'));
        $b = $this->ledger()->append($tenant, 'report.exported', 'report_export', 'reports.exports.monthly', 'p-b')->event;

        $this->assertSame('2026-07', $a->period_key);
        $this->assertSame('2026-07', $b->period_key);
        $this->assertSame(2, $this->ledger()->meterCount($tenant, 'reports.exports.monthly', '2026-07'));

        Carbon::setTestNow();
    }

    public function test_tenant_summary_returns_correct_count(): void
    {
        $tenant = Tenant::factory()->create();
        $this->ledger()->append($tenant, 'report.exported', 'report_export', 'reports.exports.monthly', 's-1');
        $this->ledger()->append($tenant, 'report.exported', 'report_export', 'reports.exports.monthly', 's-2');

        $summary = $this->ledger()->tenantSummary($tenant);
        $this->assertCount(1, $summary);
        $this->assertSame('reports.exports.monthly', $summary[0]['meter_key']);
        $this->assertSame(2, $summary[0]['events']);
    }

    public function test_cross_tenant_events_do_not_leak(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        $this->ledger()->append($a, 'report.exported', 'report_export', 'reports.exports.monthly', 'x-a');
        $this->ledger()->append($b, 'report.exported', 'report_export', 'reports.exports.monthly', 'x-b');
        // Same idempotency key allowed across tenants (uniqueness is per tenant).
        $this->ledger()->append($b, 'report.exported', 'report_export', 'reports.exports.monthly', 'x-a');

        $this->assertSame(1, $this->ledger()->monthlyMeterCount($a, 'reports.exports.monthly'));
        $this->assertSame(2, $this->ledger()->monthlyMeterCount($b, 'reports.exports.monthly'));
    }
}
