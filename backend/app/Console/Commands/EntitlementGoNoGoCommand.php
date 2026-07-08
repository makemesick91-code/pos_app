<?php

namespace App\Console\Commands;

use App\Services\Entitlements\EntitlementGoNoGoService;
use Illuminate\Console\Command;

/**
 * Sprint 32 — entitlement:go-no-go. Aggregates the entitlement governance audit,
 * the runtime-wiring checks for every core limit (ENT-R024), the Sprint 32
 * command self-contract, the cumulative Sprint 24–31 prior-gate contract
 * (ENT-R023), and the documentation contract into one GO/WATCH/NO-GO decision.
 *
 * Never prints secrets, never deploys, never charges, never calls a gateway,
 * never auto-suspends/reactivates a tenant, never runs Android Gradle. Exit code:
 * 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO.
 */
class EntitlementGoNoGoCommand extends Command
{
    protected $signature = 'entitlement:go-no-go
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Aggregate entitlement runtime-enforcement checks + prior-sprint gates into GO/WATCH/NO-GO.';

    public function handle(EntitlementGoNoGoService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Entitlement Runtime Enforcement GO / WATCH / NO-GO');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === EntitlementGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === EntitlementGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
