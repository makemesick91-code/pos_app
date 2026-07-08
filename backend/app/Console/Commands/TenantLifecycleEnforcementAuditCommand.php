<?php

namespace App\Console\Commands;

use App\Services\TenantLifecycle\TenantLifecycleEnforcementAuditService;
use Illuminate\Console\Command;

/**
 * Sprint 25 — tenant-lifecycle:enforcement-audit.
 *
 * Verifies the runtime enforcement is wired: the tenant.lifecycle alias is
 * registered, every operational tenant route carries the lifecycle guard, the
 * lifecycle config contract (statuses/blocked/rules) is complete, and no
 * automation guardrail is enabled. Any missing guard or invalid state is a
 * NO_GO. Exit code: 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO.
 */
class TenantLifecycleEnforcementAuditCommand extends Command
{
    protected $signature = 'tenant-lifecycle:enforcement-audit
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Audit that tenant lifecycle runtime enforcement (guard coverage + config) is wired.';

    public function handle(TenantLifecycleEnforcementAuditService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Tenant Lifecycle Enforcement Audit');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            foreach ($report['unguarded_operational_routes'] as $route) {
                $this->line("  UNGUARDED: {$route}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === TenantLifecycleEnforcementAuditService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === TenantLifecycleEnforcementAuditService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
