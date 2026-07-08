<?php

namespace App\Console\Commands;

use App\Services\PaymentGateway\PaymentGatewaySummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 31 — payment-gateway:provider-summary. Shows configured providers/
 * channels/status safely. Prints only env variable NAME counts, never credential
 * values (PGW-R016).
 */
class PaymentGatewayProviderSummaryCommand extends Command
{
    protected $signature = 'payment-gateway:provider-summary {--json : Output JSON}';

    protected $description = 'Show configured payment gateway providers/channels/status (no secrets).';

    public function handle(PaymentGatewaySummaryService $summaries): int
    {
        $summary = $summaries->providerSummary();

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Payment gateway providers (default: '.$summary['default_provider'].', live: '.($summary['live_gateway_enabled'] ? 'yes' : 'no').')');
        foreach ($summary['providers'] as $p) {
            $this->line(sprintf(
                '  %-10s enabled=%s live=%s channels=[%s] credential_env_count=%d',
                $p['provider'],
                $p['enabled'] ? 'yes' : 'no',
                $p['live'] ? 'yes' : 'no',
                implode(',', $p['channels']),
                $p['credential_env_count'],
            ));
        }

        return self::SUCCESS;
    }
}
