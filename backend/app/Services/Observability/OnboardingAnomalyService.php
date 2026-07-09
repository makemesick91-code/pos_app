<?php

namespace App\Services\Observability;

use App\Models\TenantProvisioningRun;
use Illuminate\Support\Carbon;

/**
 * Sprint 36 — onboarding anomaly detection (OBS-R017).
 *
 * READ-ONLY. Sources from the Sprint 33 provisioning runs
 * (tenant_provisioning_runs). Detects failed and stuck (long-running, still
 * provisioning) onboarding runs per tenant. It NEVER retries or mutates a
 * provisioning run — a retry, if wanted, is a governed Sprint 33 flow.
 */
class OnboardingAnomalyService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function detect(?int $tenantId = null): array
    {
        $t = (array) config('observability_governance.thresholds', []);
        $now = Carbon::now();
        $since = $now->copy()->subHours((int) config('observability_governance.anomaly.default_lookback_hours', 168));

        $anomalies = [];

        // Failed onboarding runs per tenant.
        $failedByTenant = TenantProvisioningRun::query()
            ->where('status', TenantProvisioningRun::STATUS_FAILED)
            ->where('created_at', '>=', $since)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('tenant_id, count(*) as c')
            ->groupBy('tenant_id')
            ->pluck('c', 'tenant_id');
        foreach ($failedByTenant as $tid => $count) {
            if ((int) $count >= (int) ($t['onboarding_failed_watch'] ?? 1)) {
                $anomalies[] = [
                    'tenant_id' => (int) $tid,
                    'anomaly_key' => 'onboarding.failed_runs',
                    'category' => 'onboarding',
                    'severity' => 'medium',
                    'reason_code' => 'onboarding_failed',
                    'summary_safe' => (int) $count.' failed onboarding run(s).',
                    'metadata' => ['failed_run_count' => (int) $count],
                ];
            }
        }

        // Stuck onboarding runs: still in a non-terminal state past the window.
        $stuckBefore = $now->copy()->subMinutes((int) ($t['onboarding_stuck_minutes'] ?? 240));
        $stuck = TenantProvisioningRun::query()
            ->whereIn('status', [
                TenantProvisioningRun::STATUS_PENDING,
                TenantProvisioningRun::STATUS_PROVISIONING,
                TenantProvisioningRun::STATUS_WAITING_PAYMENT,
            ])
            ->where('created_at', '<=', $stuckBefore)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('tenant_id, count(*) as c')
            ->groupBy('tenant_id')
            ->pluck('c', 'tenant_id');
        foreach ($stuck as $tid => $count) {
            $anomalies[] = [
                'tenant_id' => (int) $tid,
                'anomaly_key' => 'onboarding.stuck_runs',
                'category' => 'onboarding',
                'severity' => 'medium',
                'reason_code' => 'onboarding_stuck',
                'summary_safe' => (int) $count.' onboarding run(s) stuck in a non-terminal state.',
                'metadata' => ['stuck_run_count' => (int) $count],
            ];
        }

        return $anomalies;
    }
}
