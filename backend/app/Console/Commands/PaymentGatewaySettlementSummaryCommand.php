<?php

namespace App\Console\Commands;

use App\Services\PaymentGateway\PaymentGatewaySummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 31 — payment-gateway:settlement-summary. Redacted aggregate settlement
 * outcomes (settled intents/amount, open intents). No PII/secrets (PGW-R016).
 */
class PaymentGatewaySettlementSummaryCommand extends Command
{
    protected $signature = 'payment-gateway:settlement-summary
        {--tenant= : Restrict to a tenant id}
        {--json : Output JSON}';

    protected $description = 'Summarize gateway settlement outcomes (no PII/secrets).';

    public function handle(PaymentGatewaySummaryService $summaries): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $summary = $summaries->settlementSummary($tenantId);

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Gateway settlements:');
        $this->line('  settled_intents: '.$summary['settled_intents']);
        $this->line('  settled_amount: '.$summary['settled_amount']);
        $this->line('  open_intents: '.$summary['open_intents']);

        return self::SUCCESS;
    }
}
