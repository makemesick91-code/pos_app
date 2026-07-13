<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Models\TenantSupportIncident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-6 — the Platform Admin support & observability console renders over the
 * canonical Sprint 35/36 services, is read-only, and audits privileged views
 * (UIX6-R001/R003/R019).
 */
class Uix6AdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
    }

    public function test_admin_support_overview_renders(): void
    {
        $this->actingAs($this->admin, 'web')
            ->get('/admin/support')
            ->assertOk()
            ->assertSee('Pusat dukungan');

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => AdminAuditLog::ACTION_ADMIN_SUPPORT_VIEWED,
            'target_type' => AdminAuditLog::TARGET_SUPPORT_CONSOLE,
        ]);
    }

    public function test_admin_support_tenant_list_and_detail_render(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Kopi Nusantara']);

        $this->actingAs($this->admin, 'web')
            ->get('/admin/support/tenants')
            ->assertOk()
            ->assertSee('Kopi Nusantara');

        $this->actingAs($this->admin, 'web')
            ->get("/admin/support/tenants/{$tenant->id}")
            ->assertOk()
            ->assertSee('Kopi Nusantara');

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => AdminAuditLog::ACTION_ADMIN_SUPPORT_VIEWED,
            'target_type' => AdminAuditLog::TARGET_TENANT,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_admin_support_tenant_detail_shows_tenant_incidents(): void
    {
        $tenant = Tenant::factory()->create();
        TenantSupportIncident::query()->create([
            'tenant_id' => $tenant->id,
            'incident_number' => 'SUP-UIX6-1',
            'category' => 'other',
            'severity' => 'high',
            'status' => 'open',
            'title_safe' => 'Perangkat kasir gagal sinkron',
            'opened_at' => now(),
        ]);

        $this->actingAs($this->admin, 'web')
            ->get("/admin/support/tenants/{$tenant->id}")
            ->assertOk()
            ->assertSee('SUP-UIX6-1')
            ->assertSee('Perangkat kasir gagal sinkron');
    }

    public function test_admin_observability_overview_renders(): void
    {
        $this->actingAs($this->admin, 'web')
            ->get('/admin/observability')
            ->assertOk()
            ->assertSee('Observabilitas platform');

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => AdminAuditLog::ACTION_ADMIN_OBSERVABILITY_VIEWED,
            'target_type' => AdminAuditLog::TARGET_OBSERVABILITY_CONSOLE,
        ]);
    }
}
