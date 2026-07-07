<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payment;
use App\Services\Payments\Contracts\QrisGateway;
use App\Services\Payments\Data\QrisCreateRequest;
use App\Services\Payments\Data\QrisCreateResponse;
use App\Services\Payments\Data\QrisWebhookPayload;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Local/test QRIS provider. Deterministic and fully offline — it invents a
 * provider reference and a text QR payload, and validates webhooks with an
 * HMAC-SHA256 of the raw body keyed by QRIS_FAKE_WEBHOOK_SECRET. This is the
 * provider every test uses; it never performs an external network call.
 */
class FakeQrisGateway implements QrisGateway
{
    public function __construct(private readonly string $webhookSecret) {}

    public function name(): string
    {
        return Payment::PROVIDER_FAKE;
    }

    public function create(QrisCreateRequest $request): QrisCreateResponse
    {
        $reference = 'FAKE-QRIS-'.Str::upper(Str::random(16));
        $expiredAt = Carbon::now()->addMinutes($request->expiryMinutes);

        $payload = sprintf(
            'FAKE-QRIS|SALE:%s|AMOUNT:%s|REF:%s',
            $request->invoiceNumber,
            $request->amount,
            $reference,
        );

        return new QrisCreateResponse(
            providerReference: $reference,
            qrPayload: $payload,
            qrImageUrl: null,
            paymentUrl: null,
            expiredAt: $expiredAt,
            rawResponse: [
                'provider' => $this->name(),
                'reference' => $reference,
                'amount' => $request->amount,
                'expired_at' => $expiredAt->toIso8601String(),
            ],
        );
    }

    public function verifyWebhook(array $headers, array $payload, string $rawBody): bool
    {
        $provided = $this->header($headers, 'X-Fake-Qris-Signature');

        if ($provided === null || $provided === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $this->webhookSecret);

        return hash_equals($expected, $provided);
    }

    public function parseWebhook(array $headers, array $payload, string $rawBody): QrisWebhookPayload
    {
        $reference = (string) ($payload['provider_reference'] ?? '');
        $status = $this->mapStatus((string) ($payload['status'] ?? $payload['event_type'] ?? ''));

        $paidAt = null;
        if ($status === Payment::STATUS_PAID) {
            $paidAt = isset($payload['paid_at'])
                ? Carbon::parse((string) $payload['paid_at'])
                : Carbon::now();
        }

        return new QrisWebhookPayload(
            providerReference: $reference,
            status: $status,
            eventType: isset($payload['event_type']) ? (string) $payload['event_type'] : null,
            paidAt: $paidAt,
            raw: $payload,
        );
    }

    private function mapStatus(string $raw): string
    {
        return match (Str::lower($raw)) {
            'paid', 'success', 'settlement', 'payment.paid', 'capture' => Payment::STATUS_PAID,
            'pending', 'payment.pending' => Payment::STATUS_PENDING,
            'expire', 'expired', 'payment.expired' => Payment::STATUS_EXPIRED,
            'deny', 'failure', 'failed', 'payment.failed' => Payment::STATUS_FAILED,
            'cancel', 'cancelled', 'canceled', 'payment.cancelled' => Payment::STATUS_CANCELLED,
            default => Payment::STATUS_PENDING,
        };
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private function header(array $headers, string $name): ?string
    {
        // Laravel request headers arrive as arrays keyed lower-case; be lenient.
        foreach ($headers as $key => $value) {
            if (Str::lower($key) === Str::lower($name)) {
                return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
            }
        }

        return null;
    }
}
