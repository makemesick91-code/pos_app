<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-3 — SaaS Control Center dashboard + read-only tenant management.
 */
class Uix3ControlCenterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->platformAdmin()->create();
    }

    public function test_dashboard_renders_real_metric_groups(): void
    {
        Tenant::factory()->count(3)->create(['status' => Tenant::STATUS_ACTIVE]);
        Tenant::factory()->create(['status' => Tenant::STATUS_SUSPENDED]);

        $this->actingAs($this->admin(), 'web')
            ->get('/admin')
            ->assertOk()
            ->assertSee('SaaS Control Center')
            ->assertSee('Total Tenant')
            ->assertSee('Perangkat Aktif')
            ->assertSee('Kesehatan Operasional');
    }

    public function test_dashboard_shows_truthful_total_tenant_count(): void
    {
        Tenant::factory()->count(4)->create(['status' => Tenant::STATUS_ACTIVE]);

        // 4 active tenants must be reflected, not fabricated.
        $this->actingAs($this->admin(), 'web')
            ->get('/admin')
            ->assertOk()
            ->assertSee('4 aktif');
    }

    public function test_tenant_list_renders_and_is_searchable(): void
    {
        Tenant::factory()->create(['name' => 'Alpha Cafe', 'code' => 'ALPHA']);
        Tenant::factory()->create(['name' => 'Beta Bakery', 'code' => 'BETA']);
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->get('/admin/tenants')
            ->assertOk()->assertSee('Alpha Cafe')->assertSee('Beta Bakery');

        $this->actingAs($admin, 'web')->get('/admin/tenants?q=Alpha')
            ->assertOk()->assertSee('Alpha Cafe')->assertDontSee('Beta Bakery');
    }

    public function test_tenant_list_status_filter_is_whitelisted(): void
    {
        Tenant::factory()->create(['name' => 'ActiveCo', 'status' => Tenant::STATUS_ACTIVE]);
        Tenant::factory()->create(['name' => 'SuspendedCo', 'status' => Tenant::STATUS_SUSPENDED]);
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->get('/admin/tenants?status=suspended')
            ->assertOk()->assertSee('SuspendedCo')->assertDontSee('ActiveCo');

        // An invalid status is ignored (defaults to all), never errors.
        $this->actingAs($admin, 'web')->get('/admin/tenants?status=DROP%20TABLE')
            ->assertOk()->assertSee('ActiveCo')->assertSee('SuspendedCo');
    }

    public function test_tenant_list_paginates(): void
    {
        Tenant::factory()->count(25)->create();

        $this->actingAs($this->admin(), 'web')->get('/admin/tenants?per_page=20')
            ->assertOk()->assertSee('Berikutnya')->assertSee('Halaman 1 dari 2');
    }

    public function test_tenant_detail_renders(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Gamma Grill']);

        $this->actingAs($this->admin(), 'web')->get("/admin/tenants/{$tenant->id}")
            ->assertOk()
            ->assertSee('Gamma Grill')
            ->assertSee('Status Lifecycle (otoritatif)')
            ->assertSee('Langganan');
    }

    public function test_tenant_detail_404_for_unknown(): void
    {
        $this->actingAs($this->admin(), 'web')->get('/admin/tenants/999999')
            ->assertNotFound();
    }

    public function test_tenant_detail_view_is_audited(): void
    {
        $admin = $this->admin();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin, 'web')->get("/admin/tenants/{$tenant->id}")->assertOk();

        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => AdminAuditLog::ACTION_TENANT_VIEWED,
            'target_type' => 'Tenant',
            'target_id' => $tenant->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_tenant_detail_does_not_leak_password_hashes_or_secrets(): void
    {
        // A user with a password hash exists under this tenant; it must never
        // surface in the console HTML.
        $tenant = Tenant::factory()->create();
        User::factory()->tenantOwner()->create(['tenant_id' => $tenant->id]);

        $html = $this->actingAs($this->admin(), 'web')
            ->get("/admin/tenants/{$tenant->id}")->getContent();

        $this->assertStringNotContainsString('$2y$', $html);
        $this->assertStringNotContainsStringIgnoringCase('remember_token', $html);
    }

    public function test_no_tenant_mutation_routes_exist(): void
    {
        $admin = $this->admin();
        $tenant = Tenant::factory()->create();

        // UIX-3 is read-only: mutating verbs on tenant resources are not routed.
        $this->actingAs($admin, 'web')->post('/admin/tenants')->assertStatus(405);
        $this->actingAs($admin, 'web')->patch("/admin/tenants/{$tenant->id}")->assertStatus(405);
        $this->actingAs($admin, 'web')->delete("/admin/tenants/{$tenant->id}")->assertStatus(405);
    }

    public function test_authenticated_console_pages_are_not_cacheable(): void
    {
        $cacheControl = $this->actingAs($this->admin(), 'web')->get('/admin')
            ->assertOk()
            ->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', (string) $cacheControl);
        $this->assertStringContainsString('private', (string) $cacheControl);
    }
}
