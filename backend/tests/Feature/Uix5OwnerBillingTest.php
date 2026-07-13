<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBillingData;
use Tests\TestCase;

/**
 * UIX-5 — Tenant Owner Billing Center authorization, tenant isolation, and page
 * rendering. Owner billing is read-only and scoped to the owner's OWN tenant
 * (UIX5-R003/R006); a foreign or unknown invoice id is 404.
 */
class Uix5OwnerBillingTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBillingData;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->makeTenant(['name' => 'Warung Maju']);
        $this->owner = $this->makeOwner($this->tenant);
    }

    // --- Authorization -----------------------------------------------------

    public function test_guest_is_redirected_to_owner_login(): void
    {
        $this->get('/owner/billing')->assertRedirect('/owner/login');
        $this->get('/owner/billing/invoices')->assertRedirect('/owner/login');
    }

    public function test_owner_can_view_billing_center(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->get('/owner/billing')
            ->assertOk()
            ->assertSee('Pusat tagihan');
    }

    public function test_non_owner_tenant_user_is_denied(): void
    {
        $cashier = User::factory()->cashier()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($cashier, 'owner')
            ->get('/owner/billing')
            ->assertRedirect('/owner/login');
    }

    public function test_inactive_owner_is_denied(): void
    {
        $inactive = $this->makeOwner($this->tenant, ['is_active' => false]);

        $this->actingAs($inactive, 'owner')
            ->get('/owner/billing')
            ->assertRedirect('/owner/login');
    }

    public function test_platform_admin_without_owner_membership_cannot_reach_owner_billing(): void
    {
        $admin = $this->makePlatformAdmin(['tenant_id' => null]);

        // A platform-admin identity on the owner guard fails the owner predicate.
        $this->actingAs($admin, 'owner')
            ->get('/owner/billing')
            ->assertRedirect('/owner/login');
    }

    // --- Tenant isolation (IDOR) ------------------------------------------

    public function test_owner_sees_only_own_tenant_invoices_in_list(): void
    {
        $mine = $this->makeInvoice($this->tenant, ['invoice_number' => 'INV-MINE-001']);
        $otherTenant = $this->makeTenant();
        $foreign = $this->makeInvoice($otherTenant, ['invoice_number' => 'INV-FOREIGN-999']);

        $this->actingAs($this->owner, 'owner')
            ->get('/owner/billing/invoices')
            ->assertOk()
            ->assertSee('INV-MINE-001')
            ->assertDontSee('INV-FOREIGN-999');
    }

    public function test_owner_cannot_open_foreign_invoice_detail(): void
    {
        $otherTenant = $this->makeTenant();
        $foreign = $this->makeInvoice($otherTenant);

        $this->actingAs($this->owner, 'owner')
            ->get("/owner/billing/invoices/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_owner_cannot_download_foreign_invoice(): void
    {
        $otherTenant = $this->makeTenant();
        $foreign = $this->makeInvoice($otherTenant);

        $this->actingAs($this->owner, 'owner')
            ->get("/owner/billing/invoices/{$foreign->id}/download")
            ->assertNotFound();
    }

    public function test_unknown_invoice_id_is_404(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->get('/owner/billing/invoices/999999')
            ->assertNotFound();
    }

    // --- Page rendering ----------------------------------------------------

    public function test_owner_invoice_detail_renders_own_invoice(): void
    {
        $invoice = $this->makeInvoice($this->tenant, ['invoice_number' => 'INV-OWN-777']);

        $this->actingAs($this->owner, 'owner')
            ->get("/owner/billing/invoices/{$invoice->id}")
            ->assertOk()
            ->assertSee('INV-OWN-777')
            ->assertSee('Rincian faktur');
    }

    public function test_empty_invoice_list_shows_real_empty_state_not_unavailable(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->get('/owner/billing/invoices')
            ->assertOk()
            ->assertSee('Belum ada faktur');
    }

    public function test_invoice_list_paginates(): void
    {
        TenantBillingInvoice::factory()
            ->count(25)
            ->sequence(fn ($seq) => ['period_key' => sprintf('%04d-%02d', 2000 + intdiv($seq->index, 12), ($seq->index % 12) + 1)])
            ->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->owner, 'owner')
            ->get('/owner/billing/invoices')
            ->assertOk()
            ->assertSee('Halaman 1 dari');
    }

    public function test_status_filter_only_shows_matching_invoices(): void
    {
        $this->makeInvoice($this->tenant, ['invoice_number' => 'INV-ISSUED-1', 'period_key' => '2026-06', 'status' => TenantBillingInvoice::STATUS_ISSUED]);
        $this->makeInvoice($this->tenant, ['invoice_number' => 'INV-VOID-1', 'period_key' => '2026-07', 'status' => TenantBillingInvoice::STATUS_VOID]);

        $this->actingAs($this->owner, 'owner')
            ->get('/owner/billing/invoices?status=void')
            ->assertOk()
            ->assertSee('INV-VOID-1')
            ->assertDontSee('INV-ISSUED-1');
    }

    public function test_billing_center_visible_to_suspended_tenant(): void
    {
        // Billing must remain visible so a restricted tenant can still pay.
        $suspended = $this->makeTenant(['status' => Tenant::STATUS_SUSPENDED]);
        $owner = $this->makeOwner($suspended);

        $this->actingAs($owner, 'owner')
            ->get('/owner/billing')
            ->assertOk()
            ->assertSee('Pusat tagihan');
    }
}
