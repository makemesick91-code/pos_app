<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-4 — lifecycle behaviour follows the canonical TenantLifecycleService. A
 * suspended / archived tenant owner can still sign in and see their authoritative
 * status, but business-data pages degrade to a truthful restricted view rather
 * than exposing operational data (UIX4-R009/R011).
 */
class Uix4OwnerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function ownerFor(Tenant $tenant): User
    {
        return User::factory()->tenantOwner()->create(['tenant_id' => $tenant->id]);
    }

    public function test_active_tenant_dashboard_is_operational(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->ownerFor($tenant);

        $this->actingAs($owner, 'owner')
            ->get('/owner')
            ->assertOk()
            ->assertSee('Aktif');
    }

    public function test_suspended_tenant_dashboard_shows_suspended_status(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $owner = $this->ownerFor($tenant);

        $this->actingAs($owner, 'owner')
            ->get('/owner')
            ->assertOk()
            ->assertSee('Ditangguhkan')
            ->assertSee('dibatasi');
    }

    public function test_suspended_tenant_dashboard_hides_business_data(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $owner = $this->ownerFor($tenant);
        Store::factory()->count(3)->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        // The suspension status is shown, but business cards/panels (outlet
        // counts, device counts, today's sales) are not exposed while blocked.
        $this->actingAs($owner, 'owner')
            ->get('/owner')
            ->assertOk()
            ->assertSee('Ditangguhkan')
            ->assertDontSee('Outlet aktif')
            ->assertDontSee('Ringkasan penjualan hari ini');
    }

    public function test_suspended_tenant_outlets_page_is_restricted(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $owner = $this->ownerFor($tenant);
        Store::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Cabang Rahasia']);

        $this->actingAs($owner, 'owner')
            ->get('/owner/outlets')
            ->assertOk()
            ->assertSee('Akses dibatasi')
            ->assertDontSee('Cabang Rahasia');
    }

    public function test_archived_tenant_devices_page_is_restricted(): void
    {
        $tenant = Tenant::factory()->inactive()->create();
        $owner = $this->ownerFor($tenant);

        $this->actingAs($owner, 'owner')
            ->get('/owner/devices')
            ->assertOk()
            ->assertSee('Akses dibatasi');
    }

    public function test_suspended_owner_can_still_view_subscription(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $owner = $this->ownerFor($tenant);

        // Billing visibility must remain so the owner can resolve the block.
        $this->actingAs($owner, 'owner')
            ->get('/owner/subscription')
            ->assertOk()
            ->assertSee('Langganan');
    }
}
