<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_gets_tenant_and_store_from_user(): void
    {
        $tenant = Tenant::factory()->create();
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $cashier = User::factory()->cashier()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
        ]);

        $this->actingAs($cashier, 'sanctum')
            ->getJson('/api/v1/tenant-context')
            ->assertOk()
            ->assertJsonPath('tenant_id', $tenant->id)
            ->assertJsonPath('store_id', $store->id)
            ->assertJsonPath('role', User::ROLE_CASHIER)
            ->assertJsonPath('foundation', 'POS_ANDROID_SAAS_FOUNDATION');
    }

    public function test_tenant_owner_resolves_context_with_null_store(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->tenantOwner()->create([
            'tenant_id' => $tenant->id,
            'store_id' => null,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/tenant-context')
            ->assertOk()
            ->assertJsonPath('tenant_id', $tenant->id)
            ->assertJsonPath('store_id', null);
    }

    public function test_tenant_owner_can_select_own_store_via_header(): void
    {
        $tenant = Tenant::factory()->create();
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $owner = User::factory()->tenantOwner()->create([
            'tenant_id' => $tenant->id,
            'store_id' => null,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/tenant-context', ['X-Store-ID' => (string) $store->id])
            ->assertOk()
            ->assertJsonPath('store_id', $store->id);
    }

    public function test_tenant_owner_cannot_select_store_from_other_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $storeB = Store::factory()->create(['tenant_id' => $tenantB->id]);
        $ownerA = User::factory()->tenantOwner()->create([
            'tenant_id' => $tenantA->id,
            'store_id' => null,
        ]);

        $this->actingAs($ownerA, 'sanctum')
            ->getJson('/api/v1/tenant-context', ['X-Store-ID' => (string) $storeB->id])
            ->assertStatus(403);
    }

    public function test_inactive_store_selection_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $store = Store::factory()->inactive()->create(['tenant_id' => $tenant->id]);
        $owner = User::factory()->tenantOwner()->create([
            'tenant_id' => $tenant->id,
            'store_id' => null,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/tenant-context', ['X-Store-ID' => (string) $store->id])
            ->assertStatus(403);
    }

    public function test_user_with_inactive_assigned_store_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $store = Store::factory()->inactive()->create(['tenant_id' => $tenant->id]);
        $cashier = User::factory()->cashier()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
        ]);

        $this->actingAs($cashier, 'sanctum')
            ->getJson('/api/v1/tenant-context')
            ->assertStatus(403);
    }

    public function test_suspended_tenant_is_rejected_on_tenant_route(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $owner = User::factory()->tenantOwner()->create([
            'tenant_id' => $tenant->id,
            'store_id' => null,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/tenant-context')
            ->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/tenant-context')->assertStatus(401);
    }
}
