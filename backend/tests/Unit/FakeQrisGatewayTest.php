<?php

namespace Tests\Unit;

use App\Models\Payment;
use App\Services\Payments\Data\QrisCreateRequest;
use App\Services\Payments\Gateways\FakeQrisGateway;
use PHPUnit\Framework\TestCase;

/**
 * Sprint 5 — FakeQrisGateway unit tests. Pure JVM-style: no DB, no network.
 */
class FakeQrisGatewayTest extends TestCase
{
    private function gateway(string $secret = 'unit-secret'): FakeQrisGateway
    {
        return new FakeQrisGateway($secret);
    }

    private function request(): QrisCreateRequest
    {
        return new QrisCreateRequest(
            tenantId: 1,
            storeId: 1,
            saleId: 1,
            invoiceNumber: 'POS-A1-20260707-000001',
            amount: '20000.00',
            expiryMinutes: 15,
        );
    }

    public function test_create_returns_reference_payload_and_expiry(): void
    {
        $response = $this->gateway()->create($this->request());

        $this->assertStringStartsWith('FAKE-QRIS-', $response->providerReference);
        $this->assertStringContainsString('POS-A1-20260707-000001', $response->qrPayload);
        $this->assertStringContainsString('20000.00', $response->qrPayload);
        $this->assertTrue($response->expiredAt->isFuture());
    }

    public function test_verify_webhook_accepts_matching_hmac(): void
    {
        $gateway = $this->gateway('secret-123');
        $raw = json_encode(['provider_reference' => 'FAKE-QRIS-ABC', 'status' => 'PAID']);
        $sig = hash_hmac('sha256', $raw, 'secret-123');

        $this->assertTrue($gateway->verifyWebhook(['X-Fake-Qris-Signature' => $sig], [], $raw));
        $this->assertFalse($gateway->verifyWebhook(['X-Fake-Qris-Signature' => 'wrong'], [], $raw));
        $this->assertFalse($gateway->verifyWebhook([], [], $raw));
    }

    public function test_parse_webhook_maps_statuses(): void
    {
        $gateway = $this->gateway();

        $this->assertSame(Payment::STATUS_PAID, $gateway->parseWebhook([], ['status' => 'paid'], '')->status);
        $this->assertSame(Payment::STATUS_PAID, $gateway->parseWebhook([], ['status' => 'settlement'], '')->status);
        $this->assertSame(Payment::STATUS_EXPIRED, $gateway->parseWebhook([], ['status' => 'expired'], '')->status);
        $this->assertSame(Payment::STATUS_FAILED, $gateway->parseWebhook([], ['status' => 'failed'], '')->status);
        $this->assertSame(Payment::STATUS_CANCELLED, $gateway->parseWebhook([], ['status' => 'cancelled'], '')->status);
        $this->assertSame(Payment::STATUS_PENDING, $gateway->parseWebhook([], ['status' => 'pending'], '')->status);
    }

    public function test_parse_webhook_sets_paid_at_only_for_paid(): void
    {
        $gateway = $this->gateway();

        $paid = $gateway->parseWebhook([], ['status' => 'paid', 'provider_reference' => 'X'], '');
        $this->assertNotNull($paid->paidAt);

        $pending = $gateway->parseWebhook([], ['status' => 'pending', 'provider_reference' => 'X'], '');
        $this->assertNull($pending->paidAt);
    }
}
