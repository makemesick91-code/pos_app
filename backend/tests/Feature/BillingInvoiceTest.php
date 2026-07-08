<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\User;
use App\Services\Billing\BillingGovernanceException;
use App\Services\Billing\TenantInvoiceService;
use App\Services\Billing\TenantInvoiceStatusService;
use App\Services\Billing\TenantPaymentCollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 30 — invoice generation is plan-priced, idempotent, and safe
 * (BIL-R002/R003/R004/R005/R013).
 */
class BillingInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'BILL-INV']);
        $this->admin = User::factory()->platformAdmin()->create();
        $this->assignTenantPlan($this->tenant, 'starter');
    }

    private function service(): TenantInvoiceService
    {
        return app(TenantInvoiceService::class);
    }

    public function test_generates_invoice_from_active_plan_price(): void
    {
        $invoice = $this->service()->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);

        $this->assertSame('starter', $invoice->plan_key);
        $this->assertSame(99000, $invoice->total_amount);
        $this->assertSame('IDR', $invoice->currency);
        $this->assertSame('2026-07', $invoice->period_key);
        $this->assertSame(TenantBillingInvoice::STATUS_ISSUED, $invoice->status);
    }

    public function test_generation_is_idempotent_per_tenant_period(): void
    {
        $a = $this->service()->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);
        $b = $this->service()->generate($this->tenant, '2026-07', 'renewal', $this->admin);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, TenantBillingInvoice::query()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_missing_plan_pricing_refuses_no_silent_zero(): void
    {
        config()->set('billing_governance.pricing.starter', null);

        $this->expectException(BillingGovernanceException::class);
        $this->service()->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);
    }

    public function test_invoice_metadata_is_redacted(): void
    {
        $invoice = $this->service()->generate(
            $this->tenant,
            '2026-07',
            'platform_admin',
            $this->admin,
            ['note' => 'ok', 'api_token' => 'sk_live_secret', 'password' => 'x'],
        );

        $this->assertArrayHasKey('note', $invoice->metadata);
        $this->assertArrayNotHasKey('api_token', $invoice->metadata);
        $this->assertArrayNotHasKey('password', $invoice->metadata);
    }

    public function test_invoice_number_is_unique(): void
    {
        $other = Tenant::factory()->create(['code' => 'BILL-INV2']);
        $this->assignTenantPlan($other, 'growth');

        $a = $this->service()->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);
        $b = $this->service()->generate($other, '2026-07', 'platform_admin', $this->admin);

        $this->assertNotSame($a->invoice_number, $b->invoice_number);
    }

    public function test_plan_price_change_does_not_mutate_issued_invoice(): void
    {
        $invoice = $this->service()->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);

        config()->set('billing_governance.pricing.starter.amount', 150000);
        // Re-run generation — idempotent, returns the same invoice unchanged.
        $again = $this->service()->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);

        $this->assertSame(99000, $again->total_amount);
    }

    public function test_paid_invoice_cannot_be_voided(): void
    {
        $invoice = $this->service()->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);
        app(TenantPaymentCollectionService::class)
            ->record($invoice, 99000, 'manual', $this->admin, 'full');

        $this->expectException(BillingGovernanceException::class);
        $this->service()->void($invoice->refresh(), $this->admin, 'nope');
    }

    public function test_issued_invoice_can_be_cancelled_when_unpaid(): void
    {
        $invoice = $this->service()->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);
        $invoice = $this->service()->cancel($invoice, $this->admin, 'duplicate');

        $this->assertSame(TenantBillingInvoice::STATUS_CANCELLED, $invoice->status);
    }

    public function test_overdue_collection_state_when_past_due(): void
    {
        $invoice = $this->service()->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);
        $invoice->due_at = now()->subDay();
        $invoice->save();

        app(TenantInvoiceStatusService::class)->refreshCollectionState($invoice->refresh());

        $this->assertSame(TenantBillingInvoice::COLLECTION_OVERDUE, $invoice->refresh()->collection_state);
    }

    // --- admin API + command ------------------------------------------------

    public function test_platform_admin_can_generate_via_api(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/tenant-billing/invoices/generate", ['period' => '2026-07'])
            ->assertStatus(201)
            ->assertJsonPath('data.total_amount', 99000);
    }

    public function test_non_platform_admin_cannot_generate(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/tenant-billing/invoices/generate", ['period' => '2026-07'])
            ->assertForbidden();
    }

    public function test_command_dry_run_does_not_mutate(): void
    {
        $this->artisan('billing:invoice-generate', ['--tenant' => $this->tenant->id, '--period' => '2026-07'])
            ->assertExitCode(0);

        $this->assertSame(0, TenantBillingInvoice::query()->count());
    }

    public function test_command_apply_creates_invoice_and_audit_log(): void
    {
        $this->artisan('billing:invoice-generate', [
            '--tenant' => $this->tenant->id,
            '--period' => '2026-07',
            '--apply' => true,
            '--actor' => 'system',
            '--reason' => 'sprint-30',
        ])->assertExitCode(0);

        $this->assertSame(1, TenantBillingInvoice::query()->where('tenant_id', $this->tenant->id)->count());
        $this->assertTrue(
            AdminAuditLog::query()->where('action', 'billing.invoice.generated')->exists(),
        );
    }
}
