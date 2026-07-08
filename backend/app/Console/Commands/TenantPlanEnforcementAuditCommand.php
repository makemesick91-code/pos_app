<?php

namespace App\Console\Commands;

use App\Services\TenantPlan\TenantPlanEnforcementAuditService;
use Illuminate\Console\Command;

/**
 * Sprint 26 — tenant-plan:enforcement-audit.
 *
 * Audits that the entitlement/usage middleware aliases are registered, that every
 * entitlement-guarded route and usage-guarded mutation carries the required guard,
 * that tenant lifecycle enforcement runs first (TPE-R004), that the config
 * contract is complete, and that no automation guardrail is enabled. Never prints
 * secrets. Exit code: 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO.
 */
class TenantPlanEnforcementAuditCommand extends Command
{
    protected $signature = 'tenant-plan:enforcement-audit
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Audit tenant plan entitlement/usage runtime enforcement wiring and lifecycle precedence.';

    public function handle(TenantPlanEnforcementAuditService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Tenant Plan Enforcement Audit');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === TenantPlanEnforcementAuditService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === TenantPlanEnforcementAuditService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
