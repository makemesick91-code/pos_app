<?php

namespace App\Console\Commands;

use App\Services\Handover\PilotClosureService;
use Illuminate\Console\Command;

/**
 * Sprint 18 — pilot:closure-check.
 *
 * Evaluates the pilot closure readiness: final defect review, final accepted-risk
 * review, and the Sprint 17 stabilization gate, into a GO/WATCH/NO_GO closure
 * decision. Read-only — never persists, never prints secrets, never deploys,
 * never sends real alerts. Exit code: 0 — GO/WATCH (unless --strict on WATCH),
 * 1 — NO_GO / strict WATCH.
 */
class PilotClosureCheckCommand extends Command
{
    protected $signature = 'pilot:closure-check
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Evaluate pilot closure readiness into a GO/WATCH/NO_GO decision.';

    public function handle(PilotClosureService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Pilot Closure Check');
            foreach ($report['checklist'] as $key => $status) {
                $this->line("[{$status}] {$key}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === PilotClosureService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === PilotClosureService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
