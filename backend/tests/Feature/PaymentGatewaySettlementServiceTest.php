<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPayment;
use App\Models\TenantBillingPaymentIntent;
use App\Models\TenantManualSuspension;
use App\Models\User;
use App\Services\Billing\TenantInvoiceService;
use App\Services\PaymentGateway\PaymentGatewayGovernanceAuditService;
use App\Services\PaymentGateway\PaymentGatewayIntentService;
use App\Services\PaymentGateway\PaymentGatewayWebhookService;
use App\Services\PaymentGateway\Providers\MockQrisPaymentGatewayProvider;
use App\Services\TenantLifecycle\TenantSuspensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 31 — settlement flows through the Sprint 30 collection service, never
 * double-collects on replay, and NEVER lifts a manual tenant suspension
 * (PGW-R010/R012/R013).
 */
class PaymentGatewaySettlementServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private TenantBillingInvoice $invoice;

    private TenantBillingPaymentIntent $intent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'PGW-SET']);
        $this->admin = User::factory()->platformAdmin()->create();
        $this->assignTenantPlan($this->tenant, 'starter');
        $this->invoice = app(TenantInvoiceService::class)->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);
        $this->intent = app(PaymentGatewayIntentService::class)->create($this->invoice, null, null, $this->admin);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function paidEvent(): array
    {
        $mock = new MockQrisPaymentGatewayProvider;
        $payload = [
            'event_id' => 'evt_paid_'.$this->intent->id,
            'event_type' => 'payment.settled',
            'reference' => $this->intent->provider_reference,
            'status' => 'settled',
            'amount' => $this->intent->amount,
            'currency' => $this->intent->currency,
        ];

        return [$payload, ['X-Signature' => $mock->signForTesting($payload)]];
    }

    public function test_settlement_never_lifts_manual_suspension(): void
    {
        app(TenantSuspensionService::class)->suspend($this->tenant, $this->admin, 'billing hold');
        $this->assertInstanceOf(TenantManualSuspension::class, $this->tenant->refresh()->activeManualSuspension());

        [$payload, $headers] = $this->paidEvent();
        app(PaymentGatewayWebhookService::class)->ingest('mock', $payload, $headers);

        // Invoice paid …
        $this->assertSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);
        // … but the manual suspension is still active.
        $this->assertInstanceOf(TenantManualSuspension::class, $this->tenant->refresh()->activeManualSuspension());
    }

    public function test_replayed_paid_event_does_not_double_collect(): void
    {
        [$payload, $headers] = $this->paidEvent();
        app(PaymentGatewayWebhookService::class)->ingest('mock', $payload, $headers);
        app(PaymentGatewayWebhookService::class)->ingest('mock', $payload, $headers);

        $this->assertSame(1, TenantBillingPayment::query()->where('invoice_id', $this->invoice->id)->count());
        $this->assertSame(99000, (int) TenantBillingPayment::query()->where('invoice_id', $this->invoice->id)->sum('amount'));
    }

    public function test_governance_audit_stays_go_after_settlement(): void
    {
        [$payload, $headers] = $this->paidEvent();
        app(PaymentGatewayWebhookService::class)->ingest('mock', $payload, $headers);

        $report = app(PaymentGatewayGovernanceAuditService::class)->evaluate();
        $this->assertSame(PaymentGatewayGovernanceAuditService::DECISION_GO, $report['decision']);
    }

    public function test_paid_intent_is_marked_paid_with_reference(): void
    {
        [$payload, $headers] = $this->paidEvent();
        app(PaymentGatewayWebhookService::class)->ingest('mock', $payload, $headers);

        $intent = $this->intent->refresh();
        $this->assertTrue($intent->isPaid());
        $this->assertNotNull($intent->paid_at);
        $this->assertNotNull($intent->provider_reference);
    }
}
