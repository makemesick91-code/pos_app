<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantSupportIncident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-6 — the Tenant Owner support view is strictly tenant-scoped to the
 * owner's own tenant. It renders their health/incidents, never another tenant's,
 * and a foreign/unknown incident id is 404 (UIX6-R004/R005/R008/R021).
 */
class Uix6OwnerSupportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['name' => 'Toko Melati']);
        $this->owner = User::factory()->tenantOwner()->create(['tenant_id' => $this->tenant->id]);
    }

    private function incidentFor(Tenant $tenant, string $number, string $title): TenantSupportIncident
    {
        return TenantSupportIncident::query()->create([
            'tenant_id' => $tenant->id,
            'incident_number' => $number,
            'category' => 'other',
            'severity' => 'medium',
            'status' => 'open',
            'title_safe' => $title,
            'summary_safe' => 'Ringkasan aman',
            'opened_at' => now(),
        ]);
    }

    public function test_owner_support_overview_renders_own_tenant_only(): void
    {
        $this->incidentFor($this->tenant, 'SUP-OWN-1', 'Sinkron kasir tertunda');

        $this->actingAs($this->owner, 'owner')
            ->get('/owner/support')
            ->assertOk()
            ->assertSee('status operasional')
            ->assertSee('SUP-OWN-1');
    }

    public function test_owner_can_view_own_incident_detail(): void
    {
        $incident = $this->incidentFor($this->tenant, 'SUP-OWN-2', 'Perangkat perlu aktivasi ulang');

        $this->actingAs($this->owner, 'owner')
            ->get("/owner/support/incidents/{$incident->id}")
            ->assertOk()
            ->assertSee('SUP-OWN-2');
    }

    public function test_owner_cannot_view_another_tenants_incident(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->incidentFor($otherTenant, 'SUP-FOREIGN-1', 'Rahasia tenant lain');

        $this->actingAs($this->owner, 'owner')
            ->get("/owner/support/incidents/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_owner_support_does_not_render_other_tenant_incident_in_list(): void
    {
        $this->incidentFor($this->tenant, 'SUP-OWN-3', 'Milik saya');
        $otherTenant = Tenant::factory()->create();
        $this->incidentFor($otherTenant, 'SUP-OTHER-3', 'Milik tenant lain');

        $this->actingAs($this->owner, 'owner')
            ->get('/owner/support')
            ->assertOk()
            ->assertSee('SUP-OWN-3')
            ->assertDontSee('SUP-OTHER-3');
    }

    public function test_owner_support_view_exposes_no_platform_global_navigation(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->get('/owner/support')
            ->assertOk()
            ->assertDontSee('/admin/observability')
            ->assertDontSee('/admin/incidents');
    }
}
