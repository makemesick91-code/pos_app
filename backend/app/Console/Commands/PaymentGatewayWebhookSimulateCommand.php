<?php

namespace App\Console\Commands;

use App\Models\TenantBillingPaymentIntent;
use App\Services\PaymentGateway\PaymentGatewayException;
use App\Services\PaymentGateway\PaymentGatewayWebhookService;
use App\Services\PaymentGateway\Providers\MockQrisPaymentGatewayProvider;
use Illuminate\Console\Command;

/**
 * Sprint 31 — payment-gateway:webhook-simulate. Uses the deterministic mock
 * provider to build a SIGNED event for a payment intent and (with --execute)
 * ingest it through the real webhook service. Statuses: paid, failed, expired,
 * cancelled, replay, invalid-signature. Default is dry-run; output is redacted
 * (the raw signature is never printed — PGW-R016).
 */
class PaymentGatewayWebhookSimulateCommand extends Command
{
    protected $signature = 'payment-gateway:webhook-simulate
        {--intent= : Payment intent id to target}
        {--status=paid : One of: paid, failed, expired, cancelled, replay, invalid-signature}
        {--execute : Ingest the event (otherwise dry-run)}
        {--json : Output JSON}';

    protected $description = 'Simulate a signed mock gateway webhook for a payment intent (dry-run unless --execute).';

    private const PROVIDER_STATUS = [
        'paid' => 'settled',
        'failed' => 'failed',
        'expired' => 'expired',
        'cancelled' => 'cancelled',
        'replay' => 'settled',
        'invalid-signature' => 'settled',
    ];

    public function handle(MockQrisPaymentGatewayProvider $mock, PaymentGatewayWebhookService $webhooks): int
    {
        $status = (string) $this->option('status');
        if (! array_key_exists($status, self::PROVIDER_STATUS)) {
            $this->error("Unknown --status '{$status}'.");

            return self::FAILURE;
        }

        $intent = TenantBillingPaymentIntent::query()->find((int) $this->option('intent'));
        if (! $intent instanceof TenantBillingPaymentIntent) {
            $this->error('A valid --intent id is required.');

            return self::FAILURE;
        }

        $payload = [
            'event_id' => 'sim_'.$status.'_'.$intent->id,
            'event_type' => 'payment.'.$status,
            'reference' => $intent->provider_reference,
            'status' => self::PROVIDER_STATUS[$status],
            'amount' => $intent->amount,
            'currency' => $intent->currency,
            'occurred_at' => now()->toIso8601String(),
        ];

        $valid = $status !== 'invalid-signature';
        $headers = ['X-Signature' => $valid ? $mock->signForTesting($payload) : 'invalid-signature-value'];

        $out = [
            'mode' => $this->option('execute') ? 'executed' : 'dry-run',
            'intent_id' => $intent->id,
            'provider' => $intent->provider,
            'simulated_status' => $status,
            'reference' => $intent->provider_reference,
            'amount' => $intent->amount,
            'signed' => $valid, // never the signature itself
        ];

        if (! $this->option('execute')) {
            return $this->emit($out);
        }

        try {
            $event = $webhooks->ingest($intent->provider, $payload, $headers);
            $out['event_id'] = $event->id;
            $out['event_status'] = $event->status;
            $out['normalized_status'] = $event->normalized_status;

            if ($status === 'replay') {
                $replay = $webhooks->ingest($intent->provider, $payload, $headers);
                $out['replay_event_id'] = $replay->id;
                $out['replay_is_same_event'] = $replay->id === $event->id;
            }
        } catch (PaymentGatewayException $e) {
            $out['refused'] = $e->governanceCode;
            $out['message'] = $e->getMessage();
        }

        return $this->emit($out);
    }

    /**
     * @param  array<string, mixed>  $out
     */
    private function emit(array $out): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($out as $k => $v) {
            $this->line("  {$k}: ".(is_bool($v) ? ($v ? 'true' : 'false') : (string) $v));
        }

        return self::SUCCESS;
    }
}
