<?php

namespace App\Console\Commands;

use App\Services\UsageEventLedger\ReportExportMeteringSummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 27 — report-export-metering:summary. Current-month report export
 * consumption derived from the append-only ledger for the canonical
 * reports.exports.monthly meter (UEL-R006, UEL-R013). Counts only, no secrets.
 */
class ReportExportMeteringSummaryCommand extends Command
{
    protected $signature = 'report-export-metering:summary {--json : Output JSON}';

    protected $description = 'Summarize report export metering (reports.exports.monthly) from the usage event ledger.';

    public function handle(ReportExportMeteringSummaryService $service): int
    {
        $summary = $service->summary();

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Report Export Metering Summary');
        $this->line('  meter_key: '.$summary['meter_key']);
        $this->line('  meterable: '.($summary['meterable'] ? 'true' : 'false'));
        $this->line('  period_key: '.$summary['period_key']);
        $this->line('  exports_current_month: '.$summary['exports_current_month']);
        $this->line('  exports_total: '.$summary['exports_total']);
        $this->line('  tenants_current_month: '.$summary['tenants_current_month']);

        return self::SUCCESS;
    }
}
