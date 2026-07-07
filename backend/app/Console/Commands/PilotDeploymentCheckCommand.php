<?php

namespace App\Console\Commands;

use App\Services\Pilot\PilotDeploymentReadinessService;
use Illuminate\Console\Command;

/**
 * Sprint 15 — pilot:deployment-check.
 *
 * Aggregates pilot deployment/field trial docs, Sprint 13 release docs, Sprint
 * 14 RC/UAT docs, release/pilot services, required commands, the Android release
 * readiness script, the release gate decision, the pilot RC/UAT decision, and
 * the field trial evidence decision into a GO / WATCH / NO-GO decision. Does NOT
 * run Android Gradle and never performs a real deploy — CI runs
 * assembleDebug/testDebugUnitTest as the build gate. Never prints secrets.
 * Exit code: 0 — GO/WATCH (unless --strict on WATCH), 1 — NO-GO / strict WATCH.
 */
class PilotDeploymentCheckCommand extends Command
{
    protected $signature = 'pilot:deployment-check
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Aggregate the pilot deployment readiness contract (docs, services, commands, Android readiness, release gate, RC/UAT gate, field trial evidence) into a GO/WATCH/NO-GO decision.';

    public function handle(PilotDeploymentReadinessService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Pilot Deployment Check');
            foreach ($report['checks'] as $check) {
                $this->line("[{$check['status']}] {$check['key']} — {$check['message']}");
            }
            $this->line("Decision: {$report['decision']}");
        }

        return $this->exitCode($report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === PilotDeploymentReadinessService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === PilotDeploymentReadinessService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
