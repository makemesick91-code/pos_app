<?php

namespace App\Console\Commands;

use App\Services\SalesPipeline\SalesLeadActivityService;
use Illuminate\Console\Command;

/**
 * Sprint 22 — sales-pipeline:activity-summary.
 *
 * Read-only activity summary by status/type + overdue placeholder count.
 * WHATSAPP_MANUAL / EMAIL_MANUAL activities are manual notes only — the command
 * asserts manual-follow-up-only and never sends a real message, never bills, never
 * prints secrets. Exit code 0.
 */
class SalesPipelineActivitySummaryCommand extends Command
{
    protected $signature = 'sales-pipeline:activity-summary
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Summarize sales lead activities by status/type (manual follow-up only).';

    public function handle(SalesLeadActivityService $service): int
    {
        $report = $service->summary();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Sales Pipeline Activity Summary');
            $this->line('planned: '.$report['planned']);
            $this->line('done: '.$report['done']);
            $this->line('cancelled: '.$report['cancelled']);
            $this->line('overdue_placeholder: '.$report['overdue_placeholder']);
            $this->line('manual_follow_up_only: '.($report['manual_follow_up_only'] ? 'PASS' : 'FAIL'));
            $this->line('Decision: '.$report['decision']);
        }

        if ($this->option('strict') && $report['decision'] !== 'GO') {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
