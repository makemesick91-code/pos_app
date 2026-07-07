<?php

namespace App\Console\Commands;

use App\Services\Pilot\HypercareIssueTriageService;
use Illuminate\Console\Command;

/**
 * Sprint 16 — hypercare:issue-triage.
 *
 * Classifies open hypercare field issues by severity and turns the open-issue
 * picture into a GO / WATCH / NO-GO decision. Reads an optional structured issue
 * snapshot file (demo/placeholder data only). Never mutates production data,
 * never sends real alerts, and never prints secrets. Exit code: 0 — GO/WATCH
 * (unless --strict on WATCH), 1 — NO-GO / strict WATCH.
 */
class HypercareIssueTriageCommand extends Command
{
    protected $signature = 'hypercare:issue-triage
        {--json : Output JSON}
        {--strict : Fail on warnings/blockers}';

    protected $description = 'Classify open hypercare issues by severity into a GO/WATCH/NO-GO decision.';

    public function handle(HypercareIssueTriageService $service): int
    {
        $summary = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Hypercare Issue Triage');
            $this->line("Open BLOCKER: {$summary['open_blocker']}");
            $this->line("Open CRITICAL: {$summary['open_critical']}");
            $this->line("Open MAJOR: {$summary['open_major']}");
            $this->line("Decision: {$summary['decision']}");
        }

        return $this->exitCode($summary['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === HypercareIssueTriageService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === HypercareIssueTriageService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
