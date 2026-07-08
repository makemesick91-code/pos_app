<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingSummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 30 — billing:invoice-summary. Read-only, redacted counts of invoices by
 * status plus the aggregate billed amount. No per-customer PII.
 */
class BillingInvoiceSummaryCommand extends Command
{
    protected $signature = 'billing:invoice-summary
        {--tenant= : Filter by tenant id}
        {--period= : Filter by period key YYYY-MM}
        {--json : Output JSON}';

    protected $description = 'Summarize tenant billing invoices by status/period (redacted counts).';

    public function handle(BillingSummaryService $summaries): int
    {
        $summary = $summaries->invoiceSummary(
            $this->option('tenant') ? (int) $this->option('tenant') : null,
            $this->option('period') ? (string) $this->option('period') : null,
        );

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Billing Invoice Summary — total: '.$summary['total_invoices']);
        foreach ($summary['by_status'] as $status => $count) {
            $this->line("  {$status}: {$count}");
        }
        $this->line('  total_amount: '.$summary['total_amount']);

        return self::SUCCESS;
    }
}
