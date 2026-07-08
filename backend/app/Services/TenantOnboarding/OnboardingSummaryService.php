<?php

namespace App\Services\TenantOnboarding;

use App\Models\TenantProvisioningRun;
use App\Models\TenantProvisioningStep;
use Illuminate\Support\Carbon;

/**
 * Sprint 33 — deterministic, PII-free summaries over provisioning runs/steps for
 * the trial-summary and decision-summary commands and the admin governance API
 * (ONB-R022/R024). Every field is an aggregate/count or a stable code.
 */
class OnboardingSummaryService
{
    /**
     * Active/expired trial summary (onboarding:trial-summary).
     *
     * @return array<string, mixed>
     */
    public function trialSummary(): array
    {
        $now = Carbon::now();

        $withTrial = TenantProvisioningRun::query()->whereNotNull('trial_ends_at');

        $active = (clone $withTrial)->where('trial_ends_at', '>=', $now)->count();
        $expired = (clone $withTrial)->where('trial_ends_at', '<', $now)->count();

        return [
            'total_runs' => TenantProvisioningRun::query()->count(),
            'trials_total' => (clone $withTrial)->count(),
            'trials_active' => $active,
            'trials_expired' => $expired,
            'by_status' => $this->countsByStatus(),
        ];
    }

    /**
     * Failed/blocked step summary (onboarding:decision-summary). Groups failed
     * and skipped-denied steps by their stable reason code — no PII.
     *
     * @return array<string, mixed>
     */
    public function decisionSummary(): array
    {
        $failed = TenantProvisioningStep::query()
            ->where('status', TenantProvisioningStep::STATUS_FAILED)
            ->get(['step_key', 'reason_code']);

        $byReason = [];
        $byStep = [];

        foreach ($failed as $step) {
            $reason = $step->reason_code ?? 'FAILED';
            $byReason[$reason] = ($byReason[$reason] ?? 0) + 1;
            $byStep[$step->step_key] = ($byStep[$step->step_key] ?? 0) + 1;
        }

        return [
            'failed_steps_total' => $failed->count(),
            'failed_runs_total' => TenantProvisioningRun::query()
                ->where('status', TenantProvisioningRun::STATUS_FAILED)
                ->count(),
            'by_reason_code' => $byReason,
            'by_step_key' => $byStep,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function countsByStatus(): array
    {
        $counts = [];

        foreach ((array) config('onboarding_governance.run_statuses', []) as $status) {
            $counts[$status] = TenantProvisioningRun::query()->where('status', $status)->count();
        }

        return $counts;
    }
}
