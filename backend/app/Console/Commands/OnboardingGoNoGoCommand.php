<?php

namespace App\Console\Commands;

use App\Services\TenantOnboarding\OnboardingGoNoGoService;
use Illuminate\Console\Command;

/**
 * Sprint 33 — onboarding:go-no-go. The hard Sprint 33 gate (ONB-R026). Aggregates
 * the governance audit, the Sprint 33 command self-contract, the cumulative
 * Sprint 24–32 prior-gate contract, the central-orchestrator wiring, and the full
 * commercial-chain compatibility into one GO/WATCH/NO-GO decision.
 *
 * Never prints secrets, never deploys, never charges, never marks paid, never
 * lifts a suspension, never runs Android Gradle. Exit code: 0 — GO/WATCH (unless
 * --strict on WATCH), 1 — NO_GO.
 */
class OnboardingGoNoGoCommand extends Command
{
    protected $signature = 'onboarding:go-no-go {--json : Output JSON} {--strict : Fail on warnings}';

    protected $description = 'Aggregate onboarding governance + command + chain checks into GO/WATCH/NO-GO.';

    public function handle(OnboardingGoNoGoService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Tenant Onboarding GO / WATCH / NO-GO');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        if ($report['decision'] === OnboardingGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $report['decision'] === OnboardingGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
