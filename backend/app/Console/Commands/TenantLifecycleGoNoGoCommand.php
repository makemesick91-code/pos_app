<?php

namespace App\Console\Commands;

use App\Services\TenantLifecycle\TenantLifecycleGoNoGoService;
use Illuminate\Console\Command;

/**
 * Sprint 25 — tenant-lifecycle:go-no-go.
 *
 * Aggregates the cumulative Sprint 13–24 gate contract, the Sprint 25 tenant
 * lifecycle command contract, the tenant lifecycle documentation contract, the
 * Android release readiness script, and the full tenant lifecycle readiness
 * (which embeds the runtime enforcement audit) into a single GO/WATCH/NO-GO
 * decision. Never prints secrets, never deploys, never charges, never
 * auto-suspends/reactivates a tenant, never runs Android Gradle. Exit code:
 * 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO.
 */
class TenantLifecycleGoNoGoCommand extends Command
{
    protected $signature = 'tenant-lifecycle:go-no-go
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Aggregate prior-sprint gates + tenant lifecycle readiness into a GO/WATCH/NO-GO decision.';

    public function handle(TenantLifecycleGoNoGoService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Tenant Lifecycle GO / WATCH / NO-GO');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === TenantLifecycleGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === TenantLifecycleGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
