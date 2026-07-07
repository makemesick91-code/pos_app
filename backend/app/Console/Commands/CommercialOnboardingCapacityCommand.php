<?php

namespace App\Console\Commands;

use App\Models\SaasPackageCatalog;
use App\Services\Commercial\OnboardingCapacityService;
use Illuminate\Console\Command;

/**
 * Sprint 20 — commercial:onboarding-capacity.
 *
 * Reports the aggregate weekly onboarding capacity placeholders (self-guided /
 * assisted / managed) against active package onboarding levels into a
 * GO/WATCH/NO-GO decision. Uses aggregate placeholders only; never creates real
 * tenants, never touches real customer data, never prints secrets. Exit code:
 * 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO / strict WATCH.
 */
class CommercialOnboardingCapacityCommand extends Command
{
    protected $signature = 'commercial:onboarding-capacity
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Report commercial onboarding capacity into a GO/WATCH/NO-GO decision.';

    public function handle(OnboardingCapacityService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $perWeek = $report['capacity_per_week'];
            $this->line('Commercial Onboarding Capacity');
            $this->line('Self-guided/week: '.($perWeek[SaasPackageCatalog::ONBOARDING_SELF_GUIDED] ?? 0));
            $this->line('Assisted/week: '.($perWeek[SaasPackageCatalog::ONBOARDING_ASSISTED] ?? 0));
            $this->line('Managed/week: '.($perWeek[SaasPackageCatalog::ONBOARDING_MANAGED] ?? 0));
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === OnboardingCapacityService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === OnboardingCapacityService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
