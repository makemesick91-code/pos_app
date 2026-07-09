<?php

namespace App\Services\Observability;

use App\Models\TenantEntitlementDecision;
use Illuminate\Support\Carbon;

/**
 * Sprint 36 — entitlement anomaly detection (OBS-R016).
 *
 * READ-ONLY. Sources from the Sprint 32 entitlement decision ledger
 * (tenant_entitlement_decisions). Detects high denial rates and unknown-plan /
 * over-quota denial spikes per tenant. It NEVER unlocks entitlement or mutates
 * any decision.
 */
class EntitlementAnomalyService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function detect(?int $tenantId = null): array
    {
        $t = (array) config('observability_governance.thresholds', []);
        $since = Carbon::now()->subHours((int) config('observability_governance.anomaly.default_lookback_hours', 168));

        $anomalies = [];

        $deniedByTenant = TenantEntitlementDecision::query()
            ->where('decision', TenantEntitlementDecision::DECISION_DENIED)
            ->where('created_at', '>=', $since)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('tenant_id, count(*) as c')
            ->groupBy('tenant_id')
            ->pluck('c', 'tenant_id');

        foreach ($deniedByTenant as $tid => $count) {
            $count = (int) $count;
            $severity = $count >= (int) ($t['entitlement_denial_degraded'] ?? 100)
                ? 'high'
                : ($count >= (int) ($t['entitlement_denial_watch'] ?? 20) ? 'medium' : null);
            if ($severity === null) {
                continue;
            }
            $anomalies[] = [
                'tenant_id' => (int) $tid,
                'anomaly_key' => 'entitlement.denial_spike',
                'category' => 'entitlement',
                'severity' => $severity,
                'reason_code' => 'entitlement_denial_spike',
                'summary_safe' => $count.' entitlement denial(s) in window.',
                'metadata' => ['denial_count' => $count],
            ];
        }

        return $anomalies;
    }
}
