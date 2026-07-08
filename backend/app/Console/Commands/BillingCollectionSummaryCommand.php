<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingSummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 30 — billing:collection-summary. Read-only, redacted counts of invoices
 * by collection state plus aggregate billed/collected/outstanding amounts.
 */
class BillingCollectionSummaryCommand extends Command
{
    protected $signature = 'billing:collection-summary
        {--tenant= : Filter by tenant id}
        {--period= : Filter by period key YYYY-MM}
        {--json : Output JSON}';

    protected $description = 'Summarize billing collection states (pending/paid/failed/overdue/…) — redacted.';

    public function handle(BillingSummaryService $summaries): int
    {
        $summary = $summaries->collectionSummary(
            $this->option('tenant') ? (int) $this->option('tenant') : null,
            $this->option('period') ? (string) $this->option('period') : null,
        );

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Billing Collection Summary — total invoices: '.$summary['total_invoices']);
        foreach ($summary['by_collection_state'] as $state => $count) {
            $this->line("  {$state}: {$count}");
        }
        $this->line('  billed: '.$summary['total_billed_amount'].' collected: '.$summary['total_collected_amount'].' outstanding: '.$summary['total_outstanding_amount']);

        return self::SUCCESS;
    }
}
