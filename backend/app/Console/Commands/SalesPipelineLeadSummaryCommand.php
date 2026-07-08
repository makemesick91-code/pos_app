<?php

namespace App\Console\Commands;

use App\Services\SalesPipeline\SalesLeadIntakeService;
use Illuminate\Console\Command;

/**
 * Sprint 22 — sales-pipeline:lead-summary.
 *
 * Read-only lead summary by status/stage/source/priority + ready-for-onboarding
 * count. Leads are intake-only; the command never creates a tenant/user/
 * subscription/device, never bills, and never prints secrets. Exit code 0.
 */
class SalesPipelineLeadSummaryCommand extends Command
{
    protected $signature = 'sales-pipeline:lead-summary
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Summarize sales leads by status/stage/source/priority.';

    public function handle(SalesLeadIntakeService $service): int
    {
        $report = $service->summary();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Sales Pipeline Lead Summary');
            $this->line('total_leads: '.$report['total_leads']);
            $this->line('by_status: '.json_encode($report['by_status']));
            $this->line('by_stage: '.json_encode($report['by_stage']));
            $this->line('by_source: '.json_encode($report['by_source']));
            $this->line('by_priority: '.json_encode($report['by_priority']));
            $this->line('ready_for_onboarding: '.$report['ready_for_onboarding']);
            $this->line('Decision: '.$report['decision']);
        }

        if ($this->option('strict') && $report['decision'] !== 'GO') {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
