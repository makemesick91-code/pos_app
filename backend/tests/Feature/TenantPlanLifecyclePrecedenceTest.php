<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantLifecycle\TenantSuspensionService;
use App\Services\TenantPlan\TenantEntitlementOverrideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 26 — tenant lifecycle enforcement runs BEFORE entitlement/usage
 * enforcement (TPE-R004). A suspended tenant is blocked with TENANT_SUSPENDED even
 * with a valid enterprise plan, and a plan/override can never re-enable it
 * (TPE-R005).
 */
class TenantPlanLifecyclePrecedenceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-PREC']);
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'PR1']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);
    }

    private function suspend(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        app(TenantSuspensionService::class)->suspend(
            tenant: $this->tenant,
            actor: $admin,
            reason: 'Precedence test suspension.',
            reasonCategory: 'PAYMENT_OVERDUE',
        );
    }

    public function test_suspended_tenant_with_enterprise_plan_is_tenant_suspended_not_feature_denied(): void
    {
        // Enterprise plan grants reports.basic, yet suspension must win.
        $this->suspend();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/daily-sales')
            ->assertStatus(423)
            ->assertJsonPath('code', 'TENANT_SUSPENDED');
    }

    public function test_suspended_tenant_blocked_on_usage_metered_mutation_with_suspended_code(): void
    {
        $this->suspend();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/products', ['name' => 'X', 'sku' => 'SKU-X', 'selling_price' => 1000])
            ->assertStatus(423)
            ->assertJsonPath('code', 'TENANT_SUSPENDED');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_entitlement_override_cannot_reenable_a_suspended_tenant(): void
    {
        $this->suspend();

        // Even explicitly enabling the feature must not bypass the lifecycle guard.
        $admin = User::factory()->platformAdmin()->create();
        app(TenantEntitlementOverrideService::class)->set(
            tenant: $this->tenant,
            actor: $admin,
            entitlementKey: 'reports.basic',
            enabled: true,
            reason: 'Attempt to re-enable.',
            reasonCategory: 'SUPPORT',
        );

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/daily-sales')
            ->assertStatus(423)
            ->assertJsonPath('code', 'TENANT_SUSPENDED');
    }
}
