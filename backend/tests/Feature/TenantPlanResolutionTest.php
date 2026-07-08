<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantPlan;
use App\Services\TenantPlan\FeatureEntitlementService;
use App\Services\TenantPlan\TenantPlanRegistrar;
use App\Services\TenantPlan\TenantPlanResolver;
use App\Services\TenantPlan\TenantUsageLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 26 — plan resolution is a single server-side source of truth (TPE-R001):
 * an assigned plan wins, no assignment falls back to a safe restricted default,
 * and entitlement/usage decisions are computed centrally.
 */
class TenantPlanResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_registrar_syncs_the_catalogue_from_config(): void
    {
        app(TenantPlanRegistrar::class)->sync();

        foreach (['starter', 'growth', 'professional', 'enterprise'] as $key) {
            $this->assertDatabaseHas('tenant_plans', ['key' => $key]);
        }
        $this->assertDatabaseHas('plan_entitlements', ['entitlement_key' => 'pos.sales', 'enabled' => true]);
        $this->assertDatabaseHas('plan_usage_limits', ['limit_key' => 'products.max']);
    }

    public function test_factory_tenant_resolves_to_enterprise_plan(): void
    {
        $tenant = Tenant::factory()->create();

        $decision = app(TenantPlanResolver::class)->resolve($tenant);

        $this->assertSame('enterprise', $decision->planKey);
        $this->assertTrue($decision->hasExplicitAssignment);
    }

    public function test_no_plan_tenant_gets_safe_restricted_default(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->planAssignments()->delete();

        $decision = app(TenantPlanResolver::class)->resolve($tenant->refresh());

        // Safe default plan — restricted, never the unlimited enterprise plan.
        $this->assertSame((string) config('tenant_plan.default_plan'), $decision->planKey);
        $this->assertFalse($decision->hasExplicitAssignment);
        $this->assertNotSame('enterprise', $decision->planKey);
        // A restricted plan has a finite numeric products cap.
        $this->assertNotNull($decision->limit('products.max')['limit']);
    }

    public function test_entitlement_service_reflects_plan_grants(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assignTenantPlan($tenant, 'starter');

        $entitlements = app(FeatureEntitlementService::class);

        $this->assertTrue($entitlements->isEntitled($tenant, 'pos.sales'));
        $this->assertTrue($entitlements->isEntitled($tenant, 'reports.basic'));
        $this->assertFalse($entitlements->isEntitled($tenant, 'reports.advanced'));
    }

    public function test_usage_service_computes_real_current_usage(): void
    {
        $tenant = Tenant::factory()->create();
        Product::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $usage = app(TenantUsageLimitService::class);

        $this->assertSame(3, $usage->currentUsage($tenant, 'products.max'));
    }

    public function test_unlimited_plan_allows_usage_beyond_lower_plan_caps(): void
    {
        $tenant = Tenant::factory()->create(); // enterprise (unlimited)
        Product::factory()->count(5)->create(['tenant_id' => $tenant->id]);

        $decision = app(TenantUsageLimitService::class)->canUse($tenant, 'products.max', 1);

        $this->assertTrue($decision->allowed);
        $this->assertTrue($decision->unlimited);
    }

    public function test_deferred_limit_is_reported_explicitly_not_a_silent_zero(): void
    {
        $tenant = Tenant::factory()->create();

        $decision = app(TenantUsageLimitService::class)->canUse($tenant, 'reports.exports.monthly', 1);

        $this->assertTrue($decision->allowed);
        $this->assertFalse($decision->meterable);
        $this->assertNull($decision->current);
    }

    public function test_constrained_plan_blocks_when_meter_reaches_cap(): void
    {
        $tenant = Tenant::factory()->create();
        $this->makeConstrainedPlan('tiny_products', ['inventory.basic' => true], ['products.max' => 1]);
        $this->assignTenantPlan($tenant, 'tiny_products');

        Product::factory()->create(['tenant_id' => $tenant->id]);

        $decision = app(TenantUsageLimitService::class)->canUse($tenant, 'products.max', 1);

        $this->assertFalse($decision->allowed);
        $this->assertSame('USAGE_LIMIT_EXCEEDED', $decision->code);
    }

    /**
     * Build a constrained catalogue plan for denial tests.
     *
     * @param  array<string, bool>  $entitlements
     * @param  array<string, int>  $limits
     */
    private function makeConstrainedPlan(string $key, array $entitlements, array $limits): TenantPlan
    {
        app(TenantPlanRegistrar::class)->ensure();

        $plan = TenantPlan::query()->create([
            'key' => $key,
            'name' => ucfirst($key),
            'status' => TenantPlan::STATUS_ACTIVE,
        ]);

        foreach ($entitlements as $entKey => $enabled) {
            $plan->entitlements()->create(['entitlement_key' => $entKey, 'enabled' => $enabled]);
        }
        foreach ($limits as $limitKey => $value) {
            $plan->usageLimits()->create(['limit_key' => $limitKey, 'limit_value' => $value, 'unlimited' => false, 'period' => 'lifetime']);
        }

        return $plan;
    }
}
