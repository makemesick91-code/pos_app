<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantPlan;
use App\Models\User;
use App\Services\TenantLifecycle\TenantSuspensionService;
use App\Services\TenantPlan\TenantPlanRegistrar;
use App\Services\UsageEventLedger\ReportExportMeteringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 29 — governed multi-export runtime: the metered daily-sales export
 * records exactly one usage event on success, a retry does not double count, and
 * a blocked export (lifecycle / entitlement / usage limit) never counts
 * (EGC-R003..R009). This is the governed-coverage view of the Sprint 27 metering.
 */
class ExportGovernanceRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    private const EXPORT_URL = '/api/v1/reports/daily-sales/export.csv';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-EGC']);
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'EGC1']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);
    }

    private function assignPlan(int $exportCap, bool $entitled = true): void
    {
        app(TenantPlanRegistrar::class)->ensure();
        $plan = TenantPlan::query()->create([
            'key' => 'egc_'.$exportCap.'_'.($entitled ? 'ent' : 'no'),
            'name' => 'EGC Plan',
            'status' => TenantPlan::STATUS_ACTIVE,
        ]);
        $plan->entitlements()->create(['entitlement_key' => 'reports.basic', 'enabled' => $entitled]);
        $plan->usageLimits()->create(['limit_key' => 'reports.exports.monthly', 'limit_value' => $exportCap, 'unlimited' => false, 'period' => 'monthly']);
        $this->assignTenantPlan($this->tenant, $plan->key);
    }

    private function ledgerCount(): int
    {
        return app(ReportExportMeteringService::class)->currentMonthlyUsage($this->tenant->fresh());
    }

    public function test_successful_export_records_exactly_one_event(): void
    {
        $this->assignPlan(10);

        $this->actingAs($this->user, 'sanctum')->get(self::EXPORT_URL)->assertOk();

        $this->assertSame(1, $this->ledgerCount());
        $this->assertDatabaseHas('tenant_usage_events', [
            'tenant_id' => $this->tenant->id,
            'event_key' => 'report.exported',
            'event_category' => 'report_export',
            'meter_key' => 'reports.exports.monthly',
        ]);
    }

    public function test_retry_with_same_idempotency_key_does_not_double_count(): void
    {
        $this->assignPlan(10);

        $headers = ['Idempotency-Key' => 'egc-retry-1'];
        $this->actingAs($this->user, 'sanctum')->get(self::EXPORT_URL, $headers)->assertOk();
        $this->actingAs($this->user, 'sanctum')->get(self::EXPORT_URL, $headers)->assertOk();

        $this->assertSame(1, $this->ledgerCount());
    }

    public function test_suspended_tenant_export_is_blocked_and_not_counted(): void
    {
        $this->assignPlan(10);
        $admin = User::factory()->platformAdmin()->create();
        app(TenantSuspensionService::class)->suspend(
            tenant: $this->tenant,
            actor: $admin,
            reason: 'EGC runtime suspension.',
            reasonCategory: 'PAYMENT_OVERDUE',
        );

        $this->actingAs($this->user, 'sanctum')->get(self::EXPORT_URL)->assertStatus(423);
        $this->assertSame(0, $this->ledgerCount());
    }

    public function test_unentitled_tenant_export_is_blocked_and_not_counted(): void
    {
        $this->assignPlan(10, entitled: false);

        $this->actingAs($this->user, 'sanctum')->get(self::EXPORT_URL)->assertStatus(403);
        $this->assertSame(0, $this->ledgerCount());
    }

    public function test_over_quota_export_is_blocked_and_not_counted(): void
    {
        $this->assignPlan(1);

        $this->actingAs($this->user, 'sanctum')->get(self::EXPORT_URL)->assertOk();
        $this->assertSame(1, $this->ledgerCount());

        $this->actingAs($this->user, 'sanctum')->get(self::EXPORT_URL)->assertStatus(429);
        $this->assertSame(1, $this->ledgerCount());
    }

    public function test_export_metadata_is_redacted(): void
    {
        $this->assignPlan(10);

        $this->actingAs($this->user, 'sanctum')
            ->get(self::EXPORT_URL.'?password=topsecret&token=sk_live_should_be_hidden')
            ->assertOk();

        $event = \App\Models\TenantUsageEvent::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $encoded = json_encode($event->metadata);
        $this->assertStringNotContainsString('topsecret', (string) $encoded);
        $this->assertStringNotContainsString('sk_live_should_be_hidden', (string) $encoded);
    }
}
