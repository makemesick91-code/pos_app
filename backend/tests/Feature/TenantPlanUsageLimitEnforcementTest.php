<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantPlan;
use App\Models\User;
use App\Services\TenantPlan\TenantPlanRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 26 — the tenant.usage.limit guard blocks a real mutation server-side when
 * the tenant has reached its plan usage cap (TPE-R003, TPE-R009). Unlimited plans
 * pass through.
 */
class TenantPlanUsageLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-USAGE']);
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'US1']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);
    }

    private function productPayload(string $sku): array
    {
        return ['name' => 'P '.$sku, 'sku' => $sku, 'selling_price' => 1000];
    }

    private function assignProductCap(int $cap): void
    {
        app(TenantPlanRegistrar::class)->ensure();
        $plan = TenantPlan::query()->create([
            'key' => 'cap_'.$cap.'_products',
            'name' => 'Cap Plan',
            'status' => TenantPlan::STATUS_ACTIVE,
        ]);
        $plan->entitlements()->create(['entitlement_key' => 'inventory.basic', 'enabled' => true]);
        $plan->usageLimits()->create(['limit_key' => 'products.max', 'limit_value' => $cap, 'unlimited' => false, 'period' => 'lifetime']);
        $this->assignTenantPlan($this->tenant, $plan->key);
    }

    public function test_unlimited_plan_allows_product_creation(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/products', $this->productPayload('SKU-A'))
            ->assertStatus(201);
    }

    public function test_product_creation_blocked_when_limit_reached(): void
    {
        $this->assignProductCap(1);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/products', $this->productPayload('SKU-1'))
            ->assertStatus(201);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/products', $this->productPayload('SKU-2'))
            ->assertStatus(429)
            ->assertJsonPath('code', 'USAGE_LIMIT_EXCEEDED')
            ->assertJsonPath('limit', 'products.max');

        $this->assertDatabaseCount('products', 1);
    }

    public function test_reads_are_not_blocked_by_usage_limit(): void
    {
        $this->assignProductCap(0);

        // Listing products is entitled (inventory.basic) and not usage-gated.
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/products')
            ->assertOk();
    }
}
