<?php

namespace App\Console\Commands;

use App\Services\Release\ReleaseGateService;
use Illuminate\Console\Command;

/**
 * Sprint 13 — release:go-no-go.
 *
 * Aggregates backend readiness + docs/routes/commands/forbidden-files contracts
 * into a GO / WATCH / NO-GO decision. Does NOT run Android Gradle — CI runs
 * assembleDebug/testDebugUnitTest as the build gate. Never prints secrets.
 * Exit code: 0 — GO/WATCH (unless --strict on WATCH), 1 — NO-GO / strict WATCH.
 */
class ReleaseGoNoGoCommand extends Command
{
    protected $signature = 'release:go-no-go
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Aggregate backend release readiness into a GO/WATCH/NO-GO decision (docs, routes, commands, forbidden files, env safety).';

    public function handle(ReleaseGateService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Release GO/NO-GO');
            $this->line("Decision: {$report['decision']}");
            foreach ($report['checks'] as $check) {
                $this->line("[{$check['status']}] {$check['key']} — {$check['message']}");
            }
        }

        return $this->exitCode($report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === ReleaseGateService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === ReleaseGateService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
