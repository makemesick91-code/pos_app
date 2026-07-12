<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantDeviceActivation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-4 — owner console business pages render real, tenant-scoped data with
 * truthful empty/unavailable states and never expose sensitive device material
 * (UIX4-R009/R010/R016).
 */
class Uix4OwnerConsolePagesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['name' => 'Warung Maju']);
        $this->owner = User::factory()->tenantOwner()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_dashboard_renders_real_metric_groups(): void
    {
        Store::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);

        $this->actingAs($this->owner, 'owner')
            ->get('/owner')
            ->assertOk()
            ->assertSee('Warung Maju')
            ->assertSee('Outlet aktif')
            ->assertSee('Status langganan')
            ->assertSee('Ringkasan penjualan hari ini');
    }

    public function test_outlet_list_search_and_pagination(): void
    {
        Store::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Alpha Outlet']);
        Store::factory()->count(20)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->owner, 'owner')
            ->get('/owner/outlets?q=Alpha')
            ->assertOk()
            ->assertSee('Alpha Outlet');

        // 21 outlets at 15/page → pagination control present.
        $this->actingAs($this->owner, 'owner')
            ->get('/owner/outlets')
            ->assertOk()
            ->assertSee('Halaman 1 dari 2');
    }

    public function test_outlet_detail_renders_for_own_tenant(): void
    {
        $outlet = Store::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cabang Utama']);

        $this->actingAs($this->owner, 'owner')
            ->get("/owner/outlets/{$outlet->id}")
            ->assertOk()
            ->assertSee('Cabang Utama');
    }

    public function test_device_list_and_detail_never_expose_token_or_fingerprint_hash(): void
    {
        $device = TenantDeviceActivation::query()->create([
            'tenant_id' => $this->tenant->id,
            'activation_status' => TenantDeviceActivation::STATUS_ACTIVATED,
            'activation_token_hash' => hash('sha256', 'super-secret-token'),
            'device_fingerprint_hash' => hash('sha256', 'device-fingerprint'),
            'device_label' => 'Kasir 1',
        ]);

        $listResponse = $this->actingAs($this->owner, 'owner')->get('/owner/devices');
        $listResponse->assertOk()->assertSee('Kasir 1');
        $listResponse->assertDontSee(hash('sha256', 'super-secret-token'));
        $listResponse->assertDontSee(hash('sha256', 'device-fingerprint'));

        $detail = $this->actingAs($this->owner, 'owner')->get("/owner/devices/{$device->id}");
        $detail->assertOk()->assertSee('Kasir 1');
        $detail->assertDontSee(hash('sha256', 'super-secret-token'));
        $detail->assertDontSee(hash('sha256', 'device-fingerprint'));
    }

    public function test_subscription_page_shows_plan(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->get('/owner/subscription')
            ->assertOk()
            ->assertSee('Paket')
            ->assertSee('Faktur terbaru');
    }

    public function test_usage_page_shows_usage_vs_limit(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->get('/owner/usage')
            ->assertOk()
            ->assertSee('Penggunaan terhadap batas');
    }

    public function test_operations_page_renders_without_infrastructure_details(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->get('/owner/operations')
            ->assertOk()
            ->assertSee('Status operasional')
            ->assertDontSee('Traceback')
            ->assertDontSee('php8.5-fpm');
    }
}
