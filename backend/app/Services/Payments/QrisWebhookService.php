<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Services\Payments\Exceptions\PaymentGatewayException;
use Throwable;

/**
 * Processes inbound QRIS gateway webhooks. Every call persists a
 * PaymentWebhookLog row FIRST, so even invalid-signature or unknown-reference
 * callbacks are audited — but only a valid, resolvable, verified webhook is
 * allowed to mutate a payment (via PaymentStatusSynchronizer, which is
 * idempotent). Webhooks resolve a payment by provider + provider_reference only;
 * tenant scoping follows from the resolved payment, so one tenant can never
 * settle another tenant's payment.
 */
class QrisWebhookService
{
    public function __construct(
        private readonly QrisGatewayManager $manager,
        private readonly PaymentStatusSynchronizer $synchronizer,
    ) {}

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $payload
     */
    public function process(string $provider, array $headers, array $payload, string $rawBody): PaymentWebhookLog
    {
        $log = PaymentWebhookLog::create([
            'provider' => strtoupper($provider),
            'event_type' => isset($payload['event_type']) ? (string) $payload['event_type'] : null,
            'provider_reference' => isset($payload['provider_reference'])
                ? (string) $payload['provider_reference']
                : null,
            'payload' => $rawBody !== '' ? $rawBody : json_encode($payload),
            'signature_valid' => false,
            'processing_status' => PaymentWebhookLog::STATUS_RECEIVED,
        ]);

        try {
            $gateway = $this->manager->gateway($provider);
        } catch (PaymentGatewayException $e) {
            return $this->fail($log, $e->getMessage());
        }

        $valid = $gateway->verifyWebhook($headers, $payload, $rawBody);
        $log->signature_valid = $valid;

        if (! $valid) {
            // Logged, but a bad signature must never touch a payment.
            return $this->fail($log, 'Invalid webhook signature.');
        }

        try {
            $parsed = $gateway->parseWebhook($headers, $payload, $rawBody);
        } catch (Throwable $e) {
            return $this->fail($log, $e->getMessage());
        }

        $log->provider_reference = $parsed->providerReference;

        /** @var Payment|null $payment */
        $payment = Payment::query()
            ->where('method', Payment::METHOD_QRIS)
            ->where('provider', $gateway->name())
            ->where('provider_reference', $parsed->providerReference)
            ->first();

        if ($payment === null) {
            // Unknown reference — recorded and safely ignored (no payment touched).
            return $this->finish($log, PaymentWebhookLog::STATUS_IGNORED, 'Unknown provider reference.');
        }

        $log->tenant_id = $payment->tenant_id;
        $log->store_id = $payment->store_id;
        $log->payment_id = $payment->id;

        $this->synchronizer->apply(
            $payment,
            $parsed->status,
            $parsed->paidAt,
            $parsed->raw,
        );

        // Idempotent by construction: a duplicate/no-op transition still counts
        // as successfully processed so the provider stops retrying.
        return $this->finish($log, PaymentWebhookLog::STATUS_PROCESSED);
    }

    private function fail(PaymentWebhookLog $log, string $message): PaymentWebhookLog
    {
        return $this->finish($log, PaymentWebhookLog::STATUS_FAILED, $message);
    }

    private function finish(PaymentWebhookLog $log, string $status, ?string $error = null): PaymentWebhookLog
    {
        $log->processing_status = $status;
        $log->processed_at = now();
        $log->error_message = $error;
        $log->save();

        return $log;
    }
}
