<?php

namespace App\Console\Commands;

use App\Services\TenantPlan\TenantPlanReadinessService;
use Illuminate\Console\Command;

/**
 * Sprint 26 — tenant-plan:readiness.
 *
 * Evaluates the automation guardrails, the plan source-of-truth contract, the
 * persisted plan catalogue, the TPE-R rules registry, the required docs, Sprint 25
 * lifecycle coexistence, and the runtime enforcement audit into a GO/WATCH/NO-GO
 * decision. Never prints secrets. Exit code: 0 — GO/WATCH (unless --strict on
 * WATCH), 1 — NO_GO.
 */
class TenantPlanReadinessCommand extends Command
{
    protected $signature = 'tenant-plan:readiness
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Evaluate tenant plan / feature entitlement / usage limit readiness into a GO/WATCH/NO-GO decision.';

    public function handle(TenantPlanReadinessService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Tenant Plan Readiness');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === TenantPlanReadinessService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === TenantPlanReadinessService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
