<?php

namespace App\Console\Commands;

use App\Services\BillingCollection\BillingInvoiceService;
use Illuminate\Console\Command;

/**
 * Sprint 23 — billing-collection:invoice-summary.
 *
 * Summarizes SaaS billing invoices (counts by status, money totals, overdue and
 * disputed counts). Never prints secrets, never charges, never calls a payment
 * gateway. Exit code: 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO.
 */
class BillingCollectionInvoiceSummaryCommand extends Command
{
    protected $signature = 'billing-collection:invoice-summary
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Summarize SaaS billing invoices (status, totals, overdue, disputed).';

    public function handle(BillingInvoiceService $service): int
    {
        $report = $service->summary();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Billing Invoice Summary');
            $this->line('total_invoices: '.$report['total_invoices']);
            $this->line('by_status: '.json_encode($report['by_status']));
            $this->line('total_amount: '.$report['total_amount']);
            $this->line('paid_amount: '.$report['paid_amount']);
            $this->line('remaining_amount: '.$report['remaining_amount']);
            $this->line('overdue_count: '.$report['overdue_count']);
            $this->line('disputed_count: '.$report['disputed_count']);
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === 'NO_GO') {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === 'WATCH') {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
