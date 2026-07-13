<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPayment;
use App\Models\TenantBillingPaymentIntent;
use App\Models\User;
use App\Services\BillingConsole\BillingConsoleReadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsBillingData;
use Tests\TestCase;

/**
 * UIX-5 — financial integrity. Amounts are whole-rupiah integers; totals, paid,
 * and outstanding come from canonical domain methods and are never recomputed or
 * rendered as floats (UIX5-R008/R010). Invoice/QRIS/settlement states stay
 * semantically distinct (UIX5-R011/R012). The console is read-only (UIX5-R016).
 */
class Uix5BillingFinancialIntegrityTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBillingData;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->makeTenant();
        $this->owner = $this->makeOwner($this->tenant);
    }

    public function test_collected_and_outstanding_use_canonical_recorded_confirmed_only(): void
    {
        $invoice = $this->makeInvoice($this->tenant, [
            'total_amount' => 99000,
            'collection_state' => TenantBillingInvoice::COLLECTION_PENDING,
        ]);
        $this->makePayment($invoice, 40000, TenantBillingPayment::STATUS_CONFIRMED);
        // A failed payment must NEVER count toward collected revenue.
        $this->makePayment($invoice, 50000, TenantBillingPayment::STATUS_FAILED);

        $present = app(BillingConsoleReadService::class)->presentInvoice($invoice->fresh());

        $this->assertSame(40000, $present['collected_amount']);
        $this->assertSame(59000, $present['outstanding_amount']);
        $this->assertIsInt($present['total_amount']);
        $this->assertIsInt($present['outstanding_amount']);
    }

    public function test_amounts_render_with_indonesian_thousands_format(): void
    {
        $invoice = $this->makeInvoice($this->tenant, ['total_amount' => 99000, 'invoice_number' => 'INV-FMT-1']);

        $this->actingAs($this->owner, 'owner')
            ->get("/owner/billing/invoices/{$invoice->id}")
            ->assertOk()
            ->assertSee('Rp 99.000');
    }

    public function test_gateway_paid_intent_does_not_present_invoice_as_settled(): void
    {
        // The invoice collection is still pending, but a QRIS intent reached
        // "paid" at the gateway. The console must show the gateway status AND the
        // (distinct) collection status — never collapse them into "Lunas".
        $invoice = $this->makeInvoice($this->tenant, [
            'invoice_number' => 'INV-QRIS-1',
            'collection_state' => TenantBillingInvoice::COLLECTION_PENDING,
        ]);
        $this->makeIntent($invoice, TenantBillingPaymentIntent::STATUS_PAID);

        $this->actingAs($this->owner, 'owner')
            ->get("/owner/billing/invoices/{$invoice->id}")
            ->assertOk()
            ->assertSee('Dibayar (gateway)')          // intent status, truthful
            ->assertSee('Menunggu pembayaran')        // collection status, distinct
            ->assertDontSee('>Lunas</span>', false);  // no "paid" collection badge fabricated
    }

    public function test_paid_invoice_is_labelled_lunas(): void
    {
        $invoice = $this->makeInvoice($this->tenant, [
            'invoice_number' => 'INV-PAID-1',
            'collection_state' => TenantBillingInvoice::COLLECTION_PAID,
        ]);

        $this->actingAs($this->owner, 'owner')
            ->get("/owner/billing/invoices/{$invoice->id}")
            ->assertOk()
            ->assertSee('Lunas');
    }

    public function test_viewing_and_downloading_never_mutates_invoice_state(): void
    {
        $invoice = $this->makeInvoice($this->tenant, [
            'status' => TenantBillingInvoice::STATUS_ISSUED,
            'collection_state' => TenantBillingInvoice::COLLECTION_PENDING,
        ]);

        $this->actingAs($this->owner, 'owner')->get("/owner/billing/invoices/{$invoice->id}")->assertOk();
        $this->actingAs($this->owner, 'owner')->get("/owner/billing/invoices/{$invoice->id}/download")->assertOk();

        $fresh = $invoice->fresh();
        $this->assertSame(TenantBillingInvoice::STATUS_ISSUED, $fresh->status);
        $this->assertSame(TenantBillingInvoice::COLLECTION_PENDING, $fresh->collection_state);
        // No payment/intent fabricated by a read.
        $this->assertSame(0, TenantBillingPayment::query()->where('invoice_id', $invoice->id)->count());
    }

    public function test_invoice_list_does_not_issue_per_row_payment_sum_queries(): void
    {
        // 12 fully-unpaid invoices (distinct periods to satisfy the unique key).
        \App\Models\TenantBillingInvoice::factory()
            ->count(12)
            ->sequence(fn ($seq) => ['period_key' => sprintf('%04d-%02d', 2010 + intdiv($seq->index, 12), ($seq->index % 12) + 1)])
            ->create(['tenant_id' => $this->tenant->id]);

        DB::enableQueryLog();
        $this->actingAs($this->owner, 'owner')->get('/owner/billing/invoices')->assertOk();

        // The collected total is eager-summed in one aggregate subquery; a per-row
        // fallback would emit a separate `select sum("amount")` per unpaid invoice.
        // A per-row fallback is a standalone aggregate whose PRIMARY table is
        // tenant_billing_payments. The main list query also contains a payments
        // sum, but as a subquery inside a select from tenant_billing_invoices, so
        // we exclude any query that also references the invoices table.
        $perRowSums = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'sum(')
                && str_contains($q['query'], 'from "tenant_billing_payments"')
                && ! str_contains($q['query'], 'tenant_billing_invoices'))
            ->count();
        DB::disableQueryLog();

        $this->assertSame(0, $perRowSums, 'invoice list must not run a payment-sum query per row');
    }

    public function test_null_amount_renders_unavailable_not_zero(): void
    {
        // The rupiah component is the single money formatter; a null amount is a
        // truthful "Tidak tersedia", never "Rp 0".
        $rendered = view('components.rupiah', ['amount' => null])->render();

        $this->assertStringContainsString('Tidak tersedia', $rendered);
        $this->assertStringNotContainsString('Rp 0', $rendered);
    }
}
