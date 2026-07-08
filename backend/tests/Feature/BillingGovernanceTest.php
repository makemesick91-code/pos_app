<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\TenantManualSuspension;
use App\Models\User;
use App\Services\Billing\TenantInvoiceService;
use App\Services\Billing\TenantPaymentCollectionService;
use App\Services\TenantLifecycle\TenantSuspensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 30 — billing foundation rules lock, gate, and prior-sprint regressions
 * (BIL-R011/R014/R015/R016).
 */
class BillingGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_billing_rules_are_present(): void
    {
        $rules = config('billing_governance.rules');
        for ($i = 1; $i <= 16; $i++) {
            $this->assertArrayHasKey(sprintf('BIL-R%03d', $i), $rules);
        }
    }

    public function test_all_guardrail_flags_are_false(): void
    {
        foreach ([
            'invoice_amount_from_client_allowed',
            'invoice_without_plan_pricing_allowed',
            'duplicate_invoice_per_period_allowed',
            'failed_payment_marks_invoice_paid_allowed',
            'paid_invoice_lifts_manual_suspension_allowed',
            'renewal_bypasses_invoice_service_allowed',
            'plan_price_change_mutates_issued_invoice_allowed',
            'tenant_route_can_mutate_invoice_state_allowed',
        ] as $flag) {
            $this->assertFalse((bool) config('billing_governance.'.$flag), $flag.' must be false');
        }
    }

    public function test_billing_go_no_go_is_green(): void
    {
        $this->artisan('billing:go-no-go')->assertExitCode(0);
    }

    public function test_billing_governance_audit_is_green(): void
    {
        $this->artisan('billing:governance-audit')->assertExitCode(0);
    }

    public function test_read_only_billing_commands_run(): void
    {
        $this->artisan('billing:period-summary')->assertExitCode(0);
        $this->artisan('billing:invoice-summary')->assertExitCode(0);
    }

    public function test_paid_invoice_does_not_lift_manual_suspension(): void
    {
        $tenant = Tenant::factory()->create(['code' => 'BILL-SUSP']);
        $admin = User::factory()->platformAdmin()->create();
        $this->assignTenantPlan($tenant, 'starter');

        app(TenantSuspensionService::class)->suspend($tenant, $admin, 'Non-payment.');
        $this->assertTrue(
            TenantManualSuspension::query()->where('tenant_id', $tenant->id)
                ->where('status', TenantManualSuspension::STATUS_ACTIVE)->exists(),
        );

        $invoice = app(TenantInvoiceService::class)->generate($tenant, '2026-07', 'platform_admin', $admin);
        app(TenantPaymentCollectionService::class)->record($invoice, 99000, 'manual', $admin, 'full');

        $this->assertSame(TenantBillingInvoice::COLLECTION_PAID, $invoice->refresh()->collection_state);
        // Paying the invoice must NOT lift the manual suspension (BIL-R011).
        $this->assertTrue(
            TenantManualSuspension::query()->where('tenant_id', $tenant->id)
                ->where('status', TenantManualSuspension::STATUS_ACTIVE)->exists(),
        );
    }

    public function test_billing_admin_route_accessible_while_tenant_suspended(): void
    {
        $tenant = Tenant::factory()->create(['code' => 'BILL-SUSP2']);
        $admin = User::factory()->platformAdmin()->create();
        app(TenantSuspensionService::class)->suspend($tenant, $admin, 'Non-payment.');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/tenants/{$tenant->id}/tenant-billing/invoices")
            ->assertOk();
    }

    public function test_prior_sprint_gates_still_green(): void
    {
        $this->artisan('tenant-lifecycle:go-no-go')->assertExitCode(0);
        $this->artisan('tenant-plan:go-no-go')->assertExitCode(0);
        $this->artisan('export-governance:go-no-go')->assertExitCode(0);
        $this->artisan('usage-ledger:go-no-go', ['--strict' => true])->assertExitCode(0);
        $this->artisan('report-export-metering:go-no-go')->assertExitCode(0);
    }
}
