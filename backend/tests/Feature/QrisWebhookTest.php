<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Sprint 5 — QRIS webhook processing. Proves signature verification, status
 * mapping, sale reconciliation, idempotency, and that invalid/unknown callbacks
 * are logged but never mutate a payment.
 */
class QrisWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Store $store;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A1']);
        $this->cashier = User::factory()->cashier()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
        ]);
    }

    /**
     * Create a PENDING QRIS payment through the real API and return it.
     */
    private function pendingQris(int $grandTotal = 20000): Payment
    {
        $sale = Sale::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->cashier->id,
            'grand_total' => $grandTotal,
            'payment_status' => Sale::PAYMENT_STATUS_UNPAID,
        ]);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/sales/{$sale->id}/payments/qris", ['provider' => 'fake'])
            ->assertCreated();

        return Payment::findOrFail($response->json('data.id'));
    }

    private function postWebhook(array $payload, ?string $signature = null, string $provider = 'fake'): TestResponse
    {
        $raw = json_encode($payload);
        $secret = (string) config('payment_gateway.providers.fake.webhook_secret');
        $sig = $signature ?? hash_hmac('sha256', $raw, $secret);

        return $this->call(
            'POST',
            "/api/v1/webhooks/payments/{$provider}",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_FAKE_QRIS_SIGNATURE' => $sig,
            ],
            $raw,
        );
    }

    public function test_valid_paid_webhook_marks_payment_and_sale_paid(): void
    {
        $payment = $this->pendingQris();

        $this->postWebhook([
            'provider_reference' => $payment->provider_reference,
            'event_type' => 'payment.paid',
            'status' => 'PAID',
            'paid_at' => '2026-07-07T00:01:00Z',
        ])->assertOk()->assertJsonPath('data.processing_status', 'processed');

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertNotNull($payment->paid_at);

        $sale = $payment->sale;
        $this->assertSame(Sale::PAYMENT_STATUS_PAID, $sale->payment_status);
        $this->assertSame('20000.00', $sale->paid_total);
        $this->assertSame('0.00', $sale->change_total);

        $this->assertDatabaseHas('payment_webhook_logs', [
            'payment_id' => $payment->id,
            'signature_valid' => true,
            'processing_status' => 'processed',
        ]);
    }

    public function test_duplicate_paid_webhook_is_idempotent(): void
    {
        $payment = $this->pendingQris();

        $body = [
            'provider_reference' => $payment->provider_reference,
            'event_type' => 'payment.paid',
            'status' => 'PAID',
            'paid_at' => '2026-07-07T00:01:00Z',
        ];

        $this->postWebhook($body)->assertOk();
        $this->postWebhook($body)->assertOk();

        // Still exactly one PAID QRIS payment; sale settled once.
        $this->assertSame(1, Payment::where('sale_id', $payment->sale_id)->where('status', 'PAID')->count());
        $this->assertSame(Sale::PAYMENT_STATUS_PAID, $payment->sale->fresh()->payment_status);
        $this->assertDatabaseCount('payment_webhook_logs', 2);
    }

    public function test_invalid_signature_is_logged_but_does_not_update_payment(): void
    {
        $payment = $this->pendingQris();

        $this->postWebhook([
            'provider_reference' => $payment->provider_reference,
            'status' => 'PAID',
        ], signature: 'deadbeef')->assertStatus(403);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame(Sale::PAYMENT_STATUS_PENDING, $payment->sale->payment_status);

        $this->assertDatabaseHas('payment_webhook_logs', [
            'provider_reference' => $payment->provider_reference,
            'signature_valid' => false,
            'processing_status' => 'failed',
        ]);
    }

    public function test_expired_webhook_marks_payment_and_sale_expired(): void
    {
        $payment = $this->pendingQris();

        $this->postWebhook([
            'provider_reference' => $payment->provider_reference,
            'status' => 'expired',
        ])->assertOk();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_EXPIRED, $payment->status);
        $this->assertSame(Sale::PAYMENT_STATUS_EXPIRED, $payment->sale->payment_status);
    }

    public function test_failed_webhook_marks_payment_failed(): void
    {
        $payment = $this->pendingQris();

        $this->postWebhook([
            'provider_reference' => $payment->provider_reference,
            'status' => 'failed',
        ])->assertOk();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
        $this->assertSame(Sale::PAYMENT_STATUS_FAILED, $payment->sale->payment_status);
    }

    public function test_unknown_reference_is_logged_and_ignored(): void
    {
        $this->postWebhook([
            'provider_reference' => 'FAKE-QRIS-DOES-NOT-EXIST',
            'status' => 'PAID',
        ])->assertOk()->assertJsonPath('data.processing_status', 'ignored');

        $this->assertDatabaseHas('payment_webhook_logs', [
            'provider_reference' => 'FAKE-QRIS-DOES-NOT-EXIST',
            'processing_status' => 'ignored',
        ]);
    }

    public function test_paid_is_not_downgraded_by_later_pending_webhook(): void
    {
        $payment = $this->pendingQris();

        $this->postWebhook([
            'provider_reference' => $payment->provider_reference,
            'status' => 'PAID',
            'paid_at' => '2026-07-07T00:01:00Z',
        ])->assertOk();

        $this->postWebhook([
            'provider_reference' => $payment->provider_reference,
            'status' => 'pending',
        ])->assertOk();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame(Sale::PAYMENT_STATUS_PAID, $payment->sale->payment_status);
    }
}
