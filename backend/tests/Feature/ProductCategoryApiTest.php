<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategoryApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Store $storeA;
    private Store $storeB;
    private User $ownerA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->tenantB = Tenant::factory()->create(['code' => 'TENANT-B']);
        $this->storeA = Store::factory()->create(['tenant_id' => $this->tenantA->id, 'code' => 'A1']);
        $this->storeB = Store::factory()->create(['tenant_id' => $this->tenantB->id, 'code' => 'B1']);
        $this->ownerA = User::factory()->tenantOwner()->create(['tenant_id' => $this->tenantA->id]);
    }

    public function test_tenant_user_can_create_category(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/product-categories', [
                'name' => 'Minuman',
                'sort_order' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Minuman');

        $this->assertDatabaseHas('product_categories', [
            'tenant_id' => $this->tenantA->id,
            'name' => 'Minuman',
        ]);
    }

    public function test_client_cannot_assign_arbitrary_tenant_id(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/product-categories', [
                'name' => 'Spoofed',
                'tenant_id' => $this->tenantB->id,
            ])
            ->assertCreated();

        // tenant_id is derived from context, never from client input.
        $this->assertDatabaseHas('product_categories', [
            'name' => 'Spoofed',
            'tenant_id' => $this->tenantA->id,
        ]);
        $this->assertDatabaseMissing('product_categories', [
            'name' => 'Spoofed',
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    public function test_tenant_user_can_list_own_categories(): void
    {
        ProductCategory::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Mine']);
        ProductCategory::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Theirs']);

        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->getJson('/api/v1/product-categories')
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Mine'));
        $this->assertFalse($names->contains('Theirs'));
    }

    public function test_tenant_user_can_update_own_category(): void
    {
        $category = ProductCategory::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->actingAs($this->ownerA, 'sanctum')
            ->putJson("/api/v1/product-categories/{$category->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');
    }

    public function test_deleting_category_sets_inactive(): void
    {
        $category = ProductCategory::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->actingAs($this->ownerA, 'sanctum')
            ->deleteJson("/api/v1/product-categories/{$category->id}")
            ->assertOk();

        $this->assertDatabaseHas('product_categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);
    }

    public function test_tenant_user_cannot_read_category_from_other_tenant(): void
    {
        $foreign = ProductCategory::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->actingAs($this->ownerA, 'sanctum')
            ->getJson("/api/v1/product-categories/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_store_id_must_belong_to_tenant(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/product-categories', [
                'name' => 'Cross store',
                'store_id' => $this->storeB->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }
}
