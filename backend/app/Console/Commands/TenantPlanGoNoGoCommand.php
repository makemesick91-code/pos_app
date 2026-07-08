<?php

namespace App\Console\Commands;

use App\Services\TenantPlan\TenantPlanGoNoGoService;
use Illuminate\Console\Command;

/**
 * Sprint 26 — tenant-plan:go-no-go.
 *
 * Aggregates the cumulative Sprint 13–25 gate contract, the Sprint 26 command
 * contract, the tenant plan documentation contract, the Android release readiness
 * script, and the full tenant plan readiness (which embeds the runtime enforcement
 * audit) into a single GO/WATCH/NO-GO decision. Never prints secrets, never
 * deploys, never charges, never auto-suspends/reactivates a tenant, never runs
 * Android Gradle. Exit code: 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO.
 */
class TenantPlanGoNoGoCommand extends Command
{
    protected $signature = 'tenant-plan:go-no-go
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Aggregate prior-sprint gates + tenant plan readiness into a GO/WATCH/NO-GO decision.';

    public function handle(TenantPlanGoNoGoService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Tenant Plan GO / WATCH / NO-GO');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === TenantPlanGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === TenantPlanGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
