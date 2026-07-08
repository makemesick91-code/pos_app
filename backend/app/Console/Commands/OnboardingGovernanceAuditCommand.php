<?php

namespace App\Console\Commands;

use App\Services\TenantOnboarding\OnboardingGovernanceAuditService;
use Illuminate\Console\Command;

/**
 * Sprint 33 — onboarding:governance-audit. Checks the onboarding governance
 * wiring (rules registry, fail-closed, hard guardrails, trial bounds, audit
 * requirement, docs). Returns non-zero on any FAIL signal.
 */
class OnboardingGovernanceAuditCommand extends Command
{
    protected $signature = 'onboarding:governance-audit {--json : Output JSON} {--strict : Fail on warnings}';

    protected $description = 'Audit onboarding governance config/rules/guardrails wiring.';

    public function handle(OnboardingGovernanceAuditService $service): int
    {
        $signals = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode(['signals' => $signals], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Onboarding Governance Audit');
            foreach ($signals as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
        }

        foreach ($signals as $signal) {
            if ($signal['status'] === OnboardingGovernanceAuditService::STATUS_FAIL) {
                return self::FAILURE;
            }

            if ($this->option('strict') && $signal['status'] === OnboardingGovernanceAuditService::STATUS_WARN) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
