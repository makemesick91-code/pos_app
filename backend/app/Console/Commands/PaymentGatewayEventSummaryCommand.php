<?php

namespace App\Console\Commands;

use App\Services\PaymentGateway\PaymentGatewaySummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 31 — payment-gateway:event-summary. Redacted aggregate counts of stored
 * gateway events by status/normalized status. No PII/secrets (PGW-R016).
 */
class PaymentGatewayEventSummaryCommand extends Command
{
    protected $signature = 'payment-gateway:event-summary
        {--tenant= : Restrict to a tenant id}
        {--json : Output JSON}';

    protected $description = 'Summarize stored gateway events and their states (no PII/secrets).';

    public function handle(PaymentGatewaySummaryService $summaries): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $summary = $summaries->eventSummary($tenantId);

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Gateway events: total='.$summary['total'].' rejected='.$summary['rejected'].' replayed='.$summary['replayed']);
        foreach ($summary['by_status'] as $status => $count) {
            $this->line("  status {$status}: {$count}");
        }
        foreach ($summary['by_normalized_status'] as $status => $count) {
            $this->line("  normalized {$status}: {$count}");
        }

        return self::SUCCESS;
    }
}
