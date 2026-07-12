<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBillingData;
use Tests\TestCase;

/**
 * UIX-5 — Platform Admin Billing Operations authorization and platform-scoped
 * reads. Platform admins read ACROSS tenants through canonical summary services
 * behind the `platform.admin.web` gate; this is never a tenant-owner capability
 * (UIX5-R004). Read-only: no mutation routes exist (UIX5-R015/R016).
 */
class Uix5AdminBillingTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBillingData;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makePlatformAdmin();
    }

    // --- Authorization -----------------------------------------------------

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin/billing')->assertRedirect('/admin/login');
        $this->get('/admin/billing/invoices')->assertRedirect('/admin/login');
    }

    public function test_platform_admin_can_view_billing_operations(): void
    {
        $this->actingAs($this->admin, 'web')
            ->get('/admin/billing')
            ->assertOk()
            ->assertSee('Operasi penagihan');
    }

    public function test_tenant_owner_cannot_reach_admin_billing(): void
    {
        $tenant = $this->makeTenant();
        $owner = $this->makeOwner($tenant);

        $this->actingAs($owner, 'web')
            ->get('/admin/billing')
            ->assertRedirect('/admin/login');
    }

    public function test_inactive_platform_admin_is_denied(): void
    {
        $inactive = $this->makePlatformAdmin(['is_active' => false]);

        $this->actingAs($inactive, 'web')
            ->get('/admin/billing')
            ->assertRedirect('/admin/login');
    }

    // --- Platform-scoped reads --------------------------------------------

    public function test_admin_invoice_list_spans_tenants(): void
    {
        $a = $this->makeTenant();
        $b = $this->makeTenant();
        $this->makeInvoice($a, ['invoice_number' => 'INV-AAA-1']);
        $this->makeInvoice($b, ['invoice_number' => 'INV-BBB-1']);

        $this->actingAs($this->admin, 'web')
            ->get('/admin/billing/invoices')
            ->assertOk()
            ->assertSee('INV-AAA-1')
            ->assertSee('INV-BBB-1');
    }

    public function test_admin_can_open_any_tenant_invoice_detail(): void
    {
        $tenant = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant, ['invoice_number' => 'INV-DET-1']);

        $this->actingAs($this->admin, 'web')
            ->get("/admin/billing/invoices/{$invoice->id}")
            ->assertOk()
            ->assertSee('INV-DET-1');
    }

    public function test_admin_tenant_billing_panel_renders(): void
    {
        $tenant = $this->makeTenant(['name' => 'Kopi Nusantara']);
        $this->makeInvoice($tenant, ['invoice_number' => 'INV-TEN-1']);

        $this->actingAs($this->admin, 'web')
            ->get("/admin/tenants/{$tenant->id}/billing")
            ->assertOk()
            ->assertSee('Kopi Nusantara')
            ->assertSee('INV-TEN-1');
    }

    public function test_admin_can_download_any_invoice(): void
    {
        $tenant = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant, ['invoice_number' => 'INV-DL-1']);

        $this->actingAs($this->admin, 'web')
            ->get("/admin/billing/invoices/{$invoice->id}/download")
            ->assertOk()
            ->assertSee('INV-DL-1');
    }

    public function test_no_admin_billing_mutation_route_exists(): void
    {
        // Read-only surface: state-changing verbs must not be routable.
        $tenant = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant);

        $this->actingAs($this->admin, 'web')
            ->post("/admin/billing/invoices/{$invoice->id}")
            ->assertStatus(405); // Method Not Allowed — no POST route
    }
}
