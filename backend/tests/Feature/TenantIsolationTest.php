<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves tenant A can never reach tenant B's data through the tenant context.
 * This is the Sprint 1 isolation gate.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $world;

    protected function setUp(): void
    {
        parent::setUp();

        $tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
        $storeA = Store::factory()->create(['tenant_id' => $tenantA->id, 'code' => 'A1']);
        $storeB = Store::factory()->create(['tenant_id' => $tenantB->id, 'code' => 'B1']);

        $this->world = [
            'tenantA' => $tenantA,
            'tenantB' => $tenantB,
            'storeA' => $storeA,
            'storeB' => $storeB,
            'ownerA' => User::factory()->tenantOwner()->create(['tenant_id' => $tenantA->id]),
            'cashierA' => User::factory()->cashier()->create([
                'tenant_id' => $tenantA->id,
                'store_id' => $storeA->id,
            ]),
        ];
    }

    public function test_tenant_a_user_cannot_access_tenant_b_store_context(): void
    {
        $this->actingAs($this->world['ownerA'], 'sanctum')
            ->getJson('/api/v1/tenant-context', ['X-Store-ID' => (string) $this->world['storeB']->id])
            ->assertStatus(403);
    }

    public function test_tenant_a_context_never_returns_tenant_b_ids(): void
    {
        $response = $this->actingAs($this->world['cashierA'], 'sanctum')
            ->getJson('/api/v1/tenant-context')
            ->assertOk();

        $this->assertSame($this->world['tenantA']->id, $response->json('tenant_id'));
        $this->assertNotSame($this->world['tenantB']->id, $response->json('tenant_id'));
        $this->assertNotSame($this->world['storeB']->id, $response->json('store_id'));
    }

    public function test_guessing_another_tenant_store_id_fails_with_403(): void
    {
        // Cashier A tries to hijack Store B by guessing its id.
        $this->actingAs($this->world['cashierA'], 'sanctum')
            ->getJson('/api/v1/tenant-context', ['X-Store-ID' => (string) $this->world['storeB']->id])
            ->assertStatus(403);
    }

    public function test_belongs_to_tenant_helper_isolates_tenants(): void
    {
        $this->assertTrue($this->world['ownerA']->belongsToTenant($this->world['tenantA']->id));
        $this->assertFalse($this->world['ownerA']->belongsToTenant($this->world['tenantB']->id));
    }
}
