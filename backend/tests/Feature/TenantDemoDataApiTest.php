<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 12 — platform admin can seed tenant-owned demo data for an existing
 * tenant. Opening stock uses the inventory ledger, demo data is tenant/store
 * isolated, and repeated seeding is idempotent.
 */
class TenantDemoDataApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Tenant $tenant;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
        $this->tenant = Tenant::factory()->create(['code' => 'DEMO-TENANT']);
        $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'S1']);
    }

    public function test_platform_admin_can_seed_demo_data(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/demo-data", [
                'store_id' => $this->store->id,
                'seed_products' => true,
                'seed_opening_inventory' => true,
                'seed_demo_sales' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.checklist.demo_products_seeded', true)
            ->assertJsonPath('data.checklist.opening_inventory_seeded', true);

        $this->assertDatabaseHas('product_categories', ['tenant_id' => $this->tenant->id, 'name' => 'Minuman']);
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'DEMO-KOPI-SUSU']);
        $this->assertDatabaseHas('product_store_prices', [
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
        ]);
        $this->assertTrue(
            InventoryMovement::query()
                ->where('tenant_id', $this->tenant->id)
                ->where('movement_type', InventoryMovement::TYPE_OPENING)
                ->exists(),
        );
    }

    public function test_repeated_seed_is_idempotent(): void
    {
        $seed = fn () => $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/demo-data", [
                'store_id' => $this->store->id,
            ])->assertCreated();

        $seed();
        $seed();
        $seed();

        $this->assertSame(
            4,
            Product::query()->where('tenant_id', $this->tenant->id)->where('sku', 'like', 'DEMO-%')->count(),
        );
        // Opening movements: one per stock-tracked demo product (3), not multiplied.
        $this->assertSame(
            3,
            InventoryMovement::query()
                ->where('tenant_id', $this->tenant->id)
                ->where('movement_type', InventoryMovement::TYPE_OPENING)
                ->count(),
        );
    }

    public function test_store_from_other_tenant_is_rejected(): void
    {
        $otherTenant = Tenant::factory()->create(['code' => 'OTHER']);
        $otherStore = Store::factory()->create(['tenant_id' => $otherTenant->id, 'code' => 'O1']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/demo-data", [
                'store_id' => $otherStore->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }

    public function test_demo_data_does_not_leak_across_tenants(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/demo-data", ['store_id' => $this->store->id])
            ->assertCreated();

        $otherTenant = Tenant::factory()->create(['code' => 'ISOLATED']);

        $this->assertSame(0, Product::query()->where('tenant_id', $otherTenant->id)->count());
    }
}
