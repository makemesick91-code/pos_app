<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantBillingGatewayEvent;
use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPayment;
use App\Models\TenantBillingPaymentIntent;
use App\Models\User;
use App\Services\Billing\TenantInvoiceService;
use App\Services\PaymentGateway\PaymentGatewayException;
use App\Services\PaymentGateway\PaymentGatewayIntentService;
use App\Services\PaymentGateway\PaymentGatewayWebhookService;
use App\Services\PaymentGateway\Providers\MockQrisPaymentGatewayProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 31 — webhook ingestion is signature-verified, replay-idempotent, and
 * only a verified paid event settles; failed/expired/cancelled never mark paid
 * (PGW-R007/R008/R009/R010).
 */
class PaymentGatewayWebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private TenantBillingInvoice $invoice;

    private TenantBillingPaymentIntent $intent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'PGW-WH']);
        $this->admin = User::factory()->platformAdmin()->create();
        $this->assignTenantPlan($this->tenant, 'starter');
        $this->invoice = app(TenantInvoiceService::class)->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);
        $this->intent = app(PaymentGatewayIntentService::class)->create($this->invoice, null, null, $this->admin);
    }

    private function webhooks(): PaymentGatewayWebhookService
    {
        return app(PaymentGatewayWebhookService::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function signedEvent(string $providerStatus, array $overrides = []): array
    {
        $mock = new MockQrisPaymentGatewayProvider;
        $payload = array_merge([
            'event_id' => 'evt_'.$providerStatus.'_'.$this->intent->id,
            'event_type' => 'payment.'.$providerStatus,
            'reference' => $this->intent->provider_reference,
            'status' => $providerStatus,
            'amount' => $this->intent->amount,
            'currency' => $this->intent->currency,
            'occurred_at' => now()->toIso8601String(),
        ], $overrides);

        return [$payload, ['X-Signature' => $mock->signForTesting($payload)]];
    }

    public function test_valid_signed_paid_event_settles_invoice(): void
    {
        [$payload, $headers] = $this->signedEvent('settled');
        $event = $this->webhooks()->ingest('mock', $payload, $headers);

        $this->assertSame(TenantBillingGatewayEvent::STATUS_PROCESSED, $event->status);
        $this->assertSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);
        $this->assertSame(TenantBillingPaymentIntent::STATUS_PAID, $this->intent->refresh()->status);
    }

    public function test_settlement_records_a_sprint30_payment(): void
    {
        [$payload, $headers] = $this->signedEvent('settled');
        $this->webhooks()->ingest('mock', $payload, $headers);

        $this->assertSame(1, TenantBillingPayment::query()->where('invoice_id', $this->invoice->id)->count());
        $payment = TenantBillingPayment::query()->where('invoice_id', $this->invoice->id)->first();
        $this->assertSame('gateway', $payment->source);
    }

    public function test_invalid_signature_is_rejected_and_never_settles(): void
    {
        [$payload] = $this->signedEvent('settled');
        $event = $this->webhooks()->ingest('mock', $payload, ['X-Signature' => 'wrong']);

        $this->assertSame(TenantBillingGatewayEvent::STATUS_REJECTED, $event->status);
        $this->assertFalse($event->signature_verified);
        $this->assertNotSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);
    }

    public function test_missing_signature_is_rejected(): void
    {
        [$payload] = $this->signedEvent('settled');
        $event = $this->webhooks()->ingest('mock', $payload, []);

        $this->assertSame(TenantBillingGatewayEvent::STATUS_REJECTED, $event->status);
    }

    public function test_replay_is_idempotent(): void
    {
        [$payload, $headers] = $this->signedEvent('settled');
        $first = $this->webhooks()->ingest('mock', $payload, $headers);
        $second = $this->webhooks()->ingest('mock', $payload, $headers);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, TenantBillingPayment::query()->where('invoice_id', $this->invoice->id)->count());
    }

    public function test_duplicate_provider_event_id_is_safe(): void
    {
        [$payload, $headers] = $this->signedEvent('settled');
        $this->webhooks()->ingest('mock', $payload, $headers);
        // Same event_id, different amount → still detected as the same event.
        [$payload2, $headers2] = $this->signedEvent('settled', ['amount' => 1]);
        $payload2['event_id'] = $payload['event_id'];
        $this->webhooks()->ingest('mock', $payload2, $headers2);

        $this->assertSame(1, TenantBillingGatewayEvent::query()->where('provider', 'mock')->count());
    }

    public function test_unknown_provider_is_rejected(): void
    {
        [$payload, $headers] = $this->signedEvent('settled');

        $this->expectException(PaymentGatewayException::class);
        $this->webhooks()->ingest('does-not-exist', $payload, $headers);
    }

    public function test_malformed_payload_is_handled_safely(): void
    {
        $event = $this->webhooks()->ingest('mock', ['garbage' => true], []);

        // No signature + unmappable → rejected, never settles, never crashes.
        $this->assertSame(TenantBillingGatewayEvent::STATUS_REJECTED, $event->status);
        $this->assertNotSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);
    }

    public function test_failed_event_never_marks_invoice_paid(): void
    {
        [$payload, $headers] = $this->signedEvent('failed');
        $event = $this->webhooks()->ingest('mock', $payload, $headers);

        $this->assertSame(TenantBillingGatewayEvent::STATUS_PROCESSED, $event->status);
        $this->assertSame(TenantBillingPaymentIntent::STATUS_FAILED, $this->intent->refresh()->status);
        $this->assertNotSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);
        $this->assertNull($this->intent->refresh()->paid_at);
    }

    public function test_expired_event_never_marks_invoice_paid(): void
    {
        [$payload, $headers] = $this->signedEvent('expired');
        $this->webhooks()->ingest('mock', $payload, $headers);

        $this->assertSame(TenantBillingPaymentIntent::STATUS_EXPIRED, $this->intent->refresh()->status);
        $this->assertNotSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);
    }

    public function test_cancelled_event_never_marks_invoice_paid(): void
    {
        [$payload, $headers] = $this->signedEvent('cancelled');
        $this->webhooks()->ingest('mock', $payload, $headers);

        $this->assertSame(TenantBillingPaymentIntent::STATUS_CANCELLED, $this->intent->refresh()->status);
        $this->assertNotSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);
    }

    public function test_amount_mismatch_is_rejected_and_never_settles(): void
    {
        [$payload, $headers] = $this->signedEvent('settled', ['amount' => 50000]);
        $event = $this->webhooks()->ingest('mock', $payload, $headers);

        $this->assertSame(TenantBillingGatewayEvent::STATUS_REJECTED, $event->status);
        $this->assertNotSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);
    }

    public function test_overpayment_is_rejected_and_never_settles(): void
    {
        [$payload, $headers] = $this->signedEvent('settled', ['amount' => 99001]);
        $event = $this->webhooks()->ingest('mock', $payload, $headers);

        $this->assertSame(TenantBillingGatewayEvent::STATUS_REJECTED, $event->status);
        $this->assertNotSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);
    }

    public function test_no_raw_signature_is_stored_on_event(): void
    {
        [$payload, $headers] = $this->signedEvent('settled');
        $event = $this->webhooks()->ingest('mock', $payload, $headers);

        $this->assertNotSame($headers['X-Signature'], $event->signature_hash);
        $this->assertLessThanOrEqual(32, strlen((string) $event->signature_hash));
        $this->assertStringNotContainsString($headers['X-Signature'], (string) json_encode($event->toArray()));
    }
}
