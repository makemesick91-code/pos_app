<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantPlan;
use App\Models\User;
use App\Services\TenantLifecycle\TenantSuspensionService;
use App\Services\TenantPlan\TenantEntitlementOverrideService;
use App\Services\TenantPlan\TenantPlanRegistrar;
use App\Services\UsageEventLedger\ReportExportMeteringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 27 — report export metering runtime: a successful export records exactly
 * one usage event from the ledger, retries do not double count, failed/blocked
 * exports never count, and the reports.exports.monthly limit is enforced
 * server-side after lifecycle and entitlement (UEL-R006..R011).
 */
class ReportExportMeteringTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    private const EXPORT_URL = '/api/v1/reports/daily-sales/export.csv';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-EXPORT']);
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'EX1']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);
    }

    private function assignExportCap(int $cap): void
    {
        app(TenantPlanRegistrar::class)->ensure();
        $plan = TenantPlan::query()->create([
            'key' => 'cap_export_'.$cap,
            'name' => 'Cap Export Plan',
            'status' => TenantPlan::STATUS_ACTIVE,
        ]);
        $plan->entitlements()->create(['entitlement_key' => 'reports.basic', 'enabled' => true]);
        $plan->usageLimits()->create(['limit_key' => 'reports.exports.monthly', 'limit_value' => $cap, 'unlimited' => false, 'period' => 'monthly']);
        $this->assignTenantPlan($this->tenant, $plan->key);
    }

    private function suspend(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        app(TenantSuspensionService::class)->suspend(
            tenant: $this->tenant,
            actor: $admin,
            reason: 'Export metering test suspension.',
            reasonCategory: 'PAYMENT_OVERDUE',
        );
    }

    private function ledgerCount(): int
    {
        return app(ReportExportMeteringService::class)->currentMonthlyUsage($this->tenant->fresh());
    }

    public function test_reports_exports_monthly_is_now_meterable(): void
    {
        $limits = (array) config('tenant_plan.usage_limits');
        $this->assertTrue((bool) ($limits['reports.exports.monthly']['meterable'] ?? false));
    }

    public function test_successful_export_records_exactly_one_event(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->get(self::EXPORT_URL)
            ->assertOk();

        $this->assertDatabaseCount('tenant_usage_events', 1);
        $this->assertDatabaseHas('tenant_usage_events', [
            'tenant_id' => $this->tenant->id,
            'event_key' => 'report.exported',
            'meter_key' => 'reports.exports.monthly',
        ]);
        $this->assertSame(1, $this->ledgerCount());
    }

    public function test_duplicate_retry_with_idempotency_key_does_not_double_count(): void
    {
        $headers = ['Idempotency-Key' => 'export-retry-1'];

        $this->actingAs($this->user, 'sanctum')->get(self::EXPORT_URL, $headers)->assertOk();
        $this->actingAs($this->user, 'sanctum')->get(self::EXPORT_URL, $headers)->assertOk();

        $this->assertDatabaseCount('tenant_usage_events', 1);
        $this->assertSame(1, $this->ledgerCount());
    }

    public function test_failed_export_does_not_record_event(): void
    {
        // Invalid filter fails validation (422) before the controller records.
        $this->actingAs($this->user, 'sanctum')
            ->getJson(self::EXPORT_URL.'?date_from=not-a-date')
            ->assertStatus(422);

        $this->assertDatabaseCount('tenant_usage_events', 0);
        $this->assertSame(0, $this->ledgerCount());
    }

    public function test_blocked_export_over_quota_does_not_record_event(): void
    {
        $this->assignExportCap(1);

        $this->actingAs($this->user, 'sanctum')->get(self::EXPORT_URL)->assertOk();
        $this->assertSame(1, $this->ledgerCount());

        // Second export is over quota → 429 before the controller, no new event.
        $this->actingAs($this->user, 'sanctum')
            ->getJson(self::EXPORT_URL)
            ->assertStatus(429)
            ->assertJsonPath('code', 'USAGE_LIMIT_EXCEEDED')
            ->assertJsonPath('limit', 'reports.exports.monthly');

        $this->assertDatabaseCount('tenant_usage_events', 1);
    }

    public function test_usage_limit_blocks_export_when_monthly_limit_reached(): void
    {
        $this->assignExportCap(0);

        $this->actingAs($this->user, 'sanctum')
            ->getJson(self::EXPORT_URL)
            ->assertStatus(429)
            ->assertJsonPath('code', 'USAGE_LIMIT_EXCEEDED');

        $this->assertDatabaseCount('tenant_usage_events', 0);
    }

    public function test_unlimited_plan_allows_export_beyond_numeric_plan(): void
    {
        // Default factory tenant is on the unlimited enterprise plan.
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->user, 'sanctum')
                ->get(self::EXPORT_URL, ['Idempotency-Key' => 'unlimited-'.$i])
                ->assertOk();
        }

        $this->assertSame(3, $this->ledgerCount());
    }

    public function test_unentitled_tenant_gets_feature_not_entitled(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        app(TenantEntitlementOverrideService::class)->set(
            tenant: $this->tenant,
            actor: $admin,
            entitlementKey: 'reports.basic',
            enabled: false,
            reason: 'Disable for export metering test.',
            reasonCategory: 'SUPPORT',
        );

        $this->actingAs($this->user, 'sanctum')
            ->getJson(self::EXPORT_URL)
            ->assertStatus(403)
            ->assertJsonPath('code', 'FEATURE_NOT_ENTITLED');

        $this->assertDatabaseCount('tenant_usage_events', 0);
    }

    public function test_suspended_tenant_with_quota_still_blocked_with_suspended_code(): void
    {
        $this->assignExportCap(50); // valid plan, entitlement, quota available
        $this->suspend();

        $this->actingAs($this->user, 'sanctum')
            ->getJson(self::EXPORT_URL)
            ->assertStatus(423)
            ->assertJsonPath('code', 'TENANT_SUSPENDED');

        $this->assertDatabaseCount('tenant_usage_events', 0);
    }
}
