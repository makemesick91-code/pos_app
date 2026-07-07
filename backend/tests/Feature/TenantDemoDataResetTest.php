<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 12 — demo data reset is guarded (confirm_demo_reset), platform-admin
 * only, audit-logged, and NEVER deletes non-demo tenant data (only rows recorded
 * in the backend-owned demo manifest).
 */
class TenantDemoDataResetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Tenant $tenant;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
        $this->tenant = Tenant::factory()->create(['code' => 'RESET-TENANT']);
        $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'S1']);
    }

    private function seedDemo(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/demo-data", ['store_id' => $this->store->id])
            ->assertCreated();
    }

    public function test_reset_requires_confirmation(): void
    {
        $this->seedDemo();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/demo-data/reset", [
                'confirm_demo_reset' => false,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('confirm_demo_reset');

        // Demo data untouched.
        $this->assertTrue(Product::query()->where('tenant_id', $this->tenant->id)->where('sku', 'like', 'DEMO-%')->exists());
    }

    public function test_confirmed_reset_removes_demo_data(): void
    {
        $this->seedDemo();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/demo-data/reset", [
                'confirm_demo_reset' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.dry_run', false);

        $this->assertSame(
            0,
            Product::query()->where('tenant_id', $this->tenant->id)->where('sku', 'like', 'DEMO-%')->count(),
        );
    }

    public function test_reset_does_not_delete_non_demo_data(): void
    {
        $this->seedDemo();

        // A real, non-demo product created outside onboarding — never in a manifest.
        $realProduct = Product::query()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => null,
            'sku' => 'REAL-SKU-001',
            'name' => 'Real Product',
            'unit' => 'pcs',
            'cost_price' => '1000.00',
            'selling_price' => '2000.00',
            'is_stock_tracked' => true,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/demo-data/reset", [
                'confirm_demo_reset' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('products', ['id' => $realProduct->id]);
        $this->assertSame(0, Product::query()->where('sku', 'like', 'DEMO-%')->count());
    }

    public function test_dry_run_reports_counts_without_deleting(): void
    {
        $this->seedDemo();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/demo-data/reset", [
                'confirm_demo_reset' => true,
                'dry_run' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.dry_run', true)
            ->assertJsonPath('data.deleted.products', 4);

        // Nothing actually deleted.
        $this->assertSame(4, Product::query()->where('tenant_id', $this->tenant->id)->where('sku', 'like', 'DEMO-%')->count());
    }

    public function test_reset_is_audit_logged(): void
    {
        $this->seedDemo();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/demo-data/reset", [
                'confirm_demo_reset' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => AdminAuditLog::ACTION_DEMO_DATA_RESET,
            'tenant_id' => $this->tenant->id,
        ]);
    }
}
