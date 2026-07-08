<?php

namespace App\Console\Commands;

use App\Services\TenantLifecycle\TenantLifecycleReadinessService;
use Illuminate\Console\Command;

/**
 * Sprint 25 — tenant-lifecycle:readiness.
 *
 * Evaluates the automation guardrails, the lifecycle status source-of-truth
 * contract, manual suspension store availability, the runtime enforcement audit,
 * and the canonical TLS-R rules registry into a GO/WATCH/NO-GO decision. Never
 * prints secrets, never suspends/reactivates a tenant. Exit code: 0 — GO/WATCH
 * (unless --strict on WATCH), 1 — NO_GO.
 */
class TenantLifecycleReadinessCommand extends Command
{
    protected $signature = 'tenant-lifecycle:readiness
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Evaluate tenant lifecycle enforcement & manual suspension readiness into a GO/WATCH/NO-GO decision.';

    public function handle(TenantLifecycleReadinessService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Tenant Lifecycle Readiness');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === TenantLifecycleReadinessService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === TenantLifecycleReadinessService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
