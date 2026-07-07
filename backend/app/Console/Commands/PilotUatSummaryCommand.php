<?php

namespace App\Console\Commands;

use App\Services\Pilot\OperatorUatSummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 14 — pilot:uat-summary.
 *
 * Summarises the operator UAT scenario pack and issue register into a GO /
 * WATCH / NO-GO decision. Reads an optional structured UAT result file (demo
 * tenant / placeholder data only). Never prints secrets or real customer data.
 * Exit code: 0 — GO/WATCH (unless --strict on WATCH), 1 — NO-GO / strict WATCH.
 */
class PilotUatSummaryCommand extends Command
{
    protected $signature = 'pilot:uat-summary
        {--json : Output JSON}
        {--strict : Fail on warnings/blockers}';

    protected $description = 'Summarise operator UAT scenarios and open issues into a GO/WATCH/NO-GO decision.';

    public function handle(OperatorUatSummaryService $service): int
    {
        $summary = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Operator UAT Summary');
            $this->line("Total scenarios: {$summary['total_scenarios']}");
            $this->line("Required scenarios: {$summary['required_scenarios']}");
            $this->line("Blocking issues: {$summary['blocking_issues']}");
            $this->line("Decision: {$summary['decision']}");
        }

        return $this->exitCode($summary['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === OperatorUatSummaryService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === OperatorUatSummaryService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
