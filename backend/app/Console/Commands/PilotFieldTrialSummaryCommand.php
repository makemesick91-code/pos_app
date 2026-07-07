<?php

namespace App\Console\Commands;

use App\Services\Pilot\FieldTrialEvidenceService;
use Illuminate\Console\Command;

/**
 * Sprint 15 — pilot:field-trial-summary.
 *
 * Summarises the canonical field trial evidence categories and open field
 * issues into a GO / WATCH / NO-GO decision. Reads an optional structured field
 * trial result file (demo tenant / placeholder data only). Never prints secrets
 * or real customer data. Exit code: 0 — GO/WATCH (unless --strict on WATCH),
 * 1 — NO-GO / strict WATCH.
 */
class PilotFieldTrialSummaryCommand extends Command
{
    protected $signature = 'pilot:field-trial-summary
        {--json : Output JSON}
        {--strict : Fail on warnings/blockers}';

    protected $description = 'Summarise field trial evidence categories and open field issues into a GO/WATCH/NO-GO decision.';

    public function handle(FieldTrialEvidenceService $service): int
    {
        $summary = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Field Trial Evidence Summary');
            $this->line("Evidence categories: {$summary['total_categories']}");
            $this->line("Required categories: {$summary['required_categories']}");
            $this->line("Blocking issues: {$summary['blocking_issues']}");
            $this->line("Decision: {$summary['decision']}");
        }

        return $this->exitCode($summary['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === FieldTrialEvidenceService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === FieldTrialEvidenceService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
