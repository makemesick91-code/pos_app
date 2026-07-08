<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPaymentIntent;
use App\Models\User;
use App\Services\Billing\TenantInvoiceService;
use App\Services\Billing\TenantPaymentCollectionService;
use App\Services\PaymentGateway\PaymentGatewayException;
use App\Services\PaymentGateway\PaymentGatewayIntentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 31 — payment intent creation is idempotent, plan-priced, and refuses a
 * paid invoice (PGW-R003/R004/R005).
 */
class PaymentGatewayIntentServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private TenantBillingInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'PGW-INT']);
        $this->admin = User::factory()->platformAdmin()->create();
        $this->assignTenantPlan($this->tenant, 'starter'); // 99000
        $this->invoice = app(TenantInvoiceService::class)->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);
    }

    private function intents(): PaymentGatewayIntentService
    {
        return app(PaymentGatewayIntentService::class);
    }

    public function test_creates_intent_for_unpaid_invoice(): void
    {
        $intent = $this->intents()->create($this->invoice, null, null, $this->admin);

        $this->assertSame(TenantBillingPaymentIntent::STATUS_PENDING, $intent->status);
        $this->assertSame('mock', $intent->provider);
        $this->assertSame('mock_qris', $intent->channel);
        $this->assertNotNull($intent->provider_reference);
    }

    public function test_intent_amount_equals_invoice_outstanding_not_client_input(): void
    {
        $intent = $this->intents()->create($this->invoice, null, null, $this->admin);

        $this->assertSame($this->invoice->outstandingAmount(), $intent->amount);
        $this->assertSame(99000, $intent->amount);
    }

    public function test_intent_is_idempotent_while_open(): void
    {
        $a = $this->intents()->create($this->invoice, null, null, $this->admin);
        $b = $this->intents()->create($this->invoice, null, null, $this->admin);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, TenantBillingPaymentIntent::query()->where('invoice_id', $this->invoice->id)->count());
    }

    public function test_explicit_idempotency_key_returns_same_intent(): void
    {
        $a = $this->intents()->create($this->invoice, null, null, $this->admin, 'platform_admin', null, 'req-1');
        $b = $this->intents()->create($this->invoice, null, null, $this->admin, 'platform_admin', null, 'req-1');

        $this->assertSame($a->id, $b->id);
    }

    public function test_refuses_paid_invoice(): void
    {
        app(TenantPaymentCollectionService::class)->record($this->invoice, 99000, 'manual', $this->admin, 'full');
        $this->assertTrue($this->invoice->refresh()->isPaid());

        $this->expectException(PaymentGatewayException::class);
        $this->intents()->create($this->invoice->refresh(), null, null, $this->admin);
    }

    public function test_refuses_void_invoice(): void
    {
        app(TenantInvoiceService::class)->void($this->invoice, $this->admin, 'test');

        $this->expectException(PaymentGatewayException::class);
        $this->intents()->create($this->invoice->refresh(), null, null, $this->admin);
    }

    public function test_refuses_unsupported_channel(): void
    {
        $this->expectException(PaymentGatewayException::class);
        $this->intents()->create($this->invoice, 'mock', 'qris', $this->admin); // mock only offers mock_qris
    }

    public function test_refuses_unknown_provider(): void
    {
        $this->expectException(PaymentGatewayException::class);
        $this->intents()->create($this->invoice, 'nope', null, $this->admin);
    }

    public function test_refuses_disabled_live_provider(): void
    {
        // midtrans is declared but disabled and live is off — must not be resolvable.
        $this->expectException(PaymentGatewayException::class);
        $this->intents()->create($this->invoice, 'midtrans', null, $this->admin);
    }

    public function test_stores_redacted_metadata(): void
    {
        $intent = $this->intents()->create(
            $this->invoice, null, null, $this->admin, 'platform_admin',
            ['note' => 'ok', 'server_key' => 'SB-xxx', 'signature' => 'abc'],
        );

        $this->assertArrayHasKey('note', $intent->metadata);
        $this->assertArrayNotHasKey('server_key', $intent->metadata);
        $this->assertArrayNotHasKey('signature', $intent->metadata);
    }

    public function test_creates_audit_log(): void
    {
        $this->intents()->create($this->invoice, null, null, $this->admin);

        $this->assertTrue(AdminAuditLog::query()->where('action', 'payment-gateway.intent.created')->exists());
    }

    public function test_provider_reference_is_unique_per_provider(): void
    {
        $this->intents()->create($this->invoice, null, null, $this->admin);

        $dupes = TenantBillingPaymentIntent::query()
            ->whereNotNull('provider_reference')
            ->selectRaw('provider, provider_reference, COUNT(*) as c')
            ->groupBy('provider', 'provider_reference')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $this->assertCount(0, $dupes);
    }
}
