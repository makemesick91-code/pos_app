<?php

namespace App\Console\Commands;

use App\Services\Pilot\PilotReleaseCandidateService;
use Illuminate\Console\Command;

/**
 * Sprint 14 — pilot:rc-check.
 *
 * Aggregates pilot RC/UAT docs, Sprint 13 release docs, release services, pilot
 * commands, regression routes, the release gate decision, and the operator UAT
 * summary into a GO / WATCH / NO-GO decision. Does NOT run Android Gradle — CI
 * runs assembleDebug/testDebugUnitTest as the build gate. Never prints secrets.
 * Exit code: 0 — GO/WATCH (unless --strict on WATCH), 1 — NO-GO / strict WATCH.
 */
class PilotRcCheckCommand extends Command
{
    protected $signature = 'pilot:rc-check
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Aggregate the pilot release candidate contract (docs, services, commands, routes, release gate, operator UAT) into a GO/WATCH/NO-GO decision.';

    public function handle(PilotReleaseCandidateService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Pilot Release Candidate Check');
            foreach ($report['checks'] as $check) {
                $this->line("[{$check['status']}] {$check['key']} — {$check['message']}");
            }
            $this->line("Decision: {$report['decision']}");
        }

        return $this->exitCode($report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === PilotReleaseCandidateService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === PilotReleaseCandidateService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
