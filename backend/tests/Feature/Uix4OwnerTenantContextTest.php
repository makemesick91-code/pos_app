<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantDeviceActivation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-4 — tenant context is derived only from the authenticated owner's own
 * record and every read is tenant-scoped; a foreign resource is never visible
 * (UIX4-R004/R005/R006/R007).
 */
class Uix4OwnerTenantContextTest extends TestCase
{
    use RefreshDatabase;

    private function ownerFor(Tenant $tenant): User
    {
        return User::factory()->tenantOwner()->create(['tenant_id' => $tenant->id]);
    }

    public function test_single_membership_context_resolves_to_own_tenant(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Kopi Sabar']);
        $owner = $this->ownerFor($tenant);

        $this->actingAs($owner, 'owner')
            ->get('/owner')
            ->assertOk()
            ->assertSee('Kopi Sabar');
    }

    public function test_owner_cannot_view_foreign_tenant_outlet(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $ownerA = $this->ownerFor($tenantA);

        $foreignOutlet = Store::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Cabang B']);

        $this->actingAs($ownerA, 'owner')
            ->get("/owner/outlets/{$foreignOutlet->id}")
            ->assertNotFound();
    }

    public function test_owner_cannot_view_foreign_tenant_device(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $ownerA = $this->ownerFor($tenantA);

        $foreignDevice = TenantDeviceActivation::query()->create([
            'tenant_id' => $tenantB->id,
            'activation_status' => TenantDeviceActivation::STATUS_ACTIVATED,
            'device_label' => 'Kasir B',
        ]);

        $this->actingAs($ownerA, 'owner')
            ->get("/owner/devices/{$foreignDevice->id}")
            ->assertNotFound();
    }

    public function test_outlet_search_cannot_leak_foreign_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $ownerA = $this->ownerFor($tenantA);

        Store::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'Cabang Alpha']);
        Store::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Cabang Bravo']);

        $this->actingAs($ownerA, 'owner')
            ->get('/owner/outlets?q=Cabang')
            ->assertOk()
            ->assertSee('Cabang Alpha')
            ->assertDontSee('Cabang Bravo');
    }

    public function test_dashboard_counts_are_tenant_scoped(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $ownerA = $this->ownerFor($tenantA);

        Store::factory()->count(2)->create(['tenant_id' => $tenantA->id, 'is_active' => true]);
        Store::factory()->count(5)->create(['tenant_id' => $tenantB->id, 'is_active' => true]);

        // The active-outlet card shows A's real count of 2, not B's 5 or a total of 7.
        $response = $this->actingAs($ownerA, 'owner')->get('/owner');
        $response->assertOk();
        $response->assertSee('dari 2 outlet');
    }

    public function test_owner_with_removed_tenant_is_denied(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->ownerFor($tenant);

        // Membership removed after the session existed.
        $owner->forceFill(['tenant_id' => null])->save();

        $this->actingAs($owner, 'owner')
            ->get('/owner')
            ->assertRedirect(route('owner.login'));

        $this->assertGuest('owner');
    }
}
