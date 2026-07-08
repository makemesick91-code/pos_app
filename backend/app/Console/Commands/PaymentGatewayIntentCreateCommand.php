<?php

namespace App\Console\Commands;

use App\Models\TenantBillingInvoice;
use App\Models\User;
use App\Services\PaymentGateway\PaymentGatewayException;
use App\Services\PaymentGateway\PaymentGatewayIntentService;
use Illuminate\Console\Command;

/**
 * Sprint 31 — payment-gateway:intent-create. Default DRY-RUN; only `--execute`
 * persists a real intent (which contacts the deterministic mock provider, never a
 * network). Idempotent per invoice + provider + channel (PGW-R003); refuses a
 * paid invoice (PGW-R004). Output is redacted (no secrets).
 */
class PaymentGatewayIntentCreateCommand extends Command
{
    protected $signature = 'payment-gateway:intent-create
        {--invoice= : Tenant billing invoice id}
        {--provider= : Provider key (default: configured default)}
        {--channel= : Channel (default: provider first channel)}
        {--execute : Persist the intent (otherwise dry-run)}
        {--json : Output JSON}';

    protected $description = 'Create (or dry-run) a payment intent for a tenant billing invoice (dry-run unless --execute).';

    public function handle(PaymentGatewayIntentService $intents): int
    {
        $invoiceId = $this->option('invoice');
        if (! $invoiceId) {
            $this->error('An --invoice id is required.');

            return self::FAILURE;
        }

        $invoice = TenantBillingInvoice::query()->find((int) $invoiceId);
        if (! $invoice instanceof TenantBillingInvoice) {
            $this->error("Invoice {$invoiceId} not found.");

            return self::FAILURE;
        }

        $provider = $this->option('provider') ? (string) $this->option('provider') : null;
        $channel = $this->option('channel') ? (string) $this->option('channel') : null;
        $execute = (bool) $this->option('execute');

        try {
            if (! $execute) {
                $result = $intents->preview($invoice, $provider, $channel);
                $result['mode'] = 'dry-run';
            } else {
                $actor = User::query()->where('is_platform_admin', true)->orderBy('id')->first();
                $intent = $intents->create($invoice, $provider, $channel, $actor, 'cli');
                $result = [
                    'mode' => 'executed',
                    'intent_id' => $intent->id,
                    'invoice_id' => $invoice->id,
                    'provider' => $intent->provider,
                    'channel' => $intent->channel,
                    'amount' => $intent->amount,
                    'currency' => $intent->currency,
                    'status' => $intent->status,
                    'provider_reference' => $intent->provider_reference,
                ];
            }
        } catch (PaymentGatewayException $e) {
            $result = ['mode' => $execute ? 'executed' : 'dry-run', 'refused' => $e->governanceCode, 'message' => $e->getMessage()];
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($result as $k => $v) {
            $this->line("  {$k}: ".(is_bool($v) ? ($v ? 'true' : 'false') : (string) $v));
        }

        return self::SUCCESS;
    }
}
