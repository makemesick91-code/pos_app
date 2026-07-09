<?php

namespace App\Services\Observability;

use App\Models\TenantEntitlementDecision;
use Illuminate\Support\Carbon;

/**
 * Sprint 36 — export/report anomaly detection (OBS-R019).
 *
 * READ-ONLY. Sources from the Sprint 32 entitlement decision ledger, filtered to
 * export/report surfaces (the Sprint 27–29 metering/governance decisions are
 * recorded there). Detects repeated export/report denials per tenant. It NEVER
 * bypasses metering or governance — it only reads the audit ledger.
 */
class ExportReportAnomalyService
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
            ->where(function ($q) {
                $q->where('resource_type', 'like', '%export%')
                    ->orWhere('resource_type', 'like', '%report%')
                    ->orWhere('entitlement_key', 'like', '%export%')
                    ->orWhere('entitlement_key', 'like', '%report%')
                    ->orWhere('action', 'like', '%export%');
            })
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('tenant_id, count(*) as c')
            ->groupBy('tenant_id')
            ->pluck('c', 'tenant_id');

        foreach ($deniedByTenant as $tid => $count) {
            if ((int) $count >= (int) ($t['export_denial_watch'] ?? 10)) {
                $anomalies[] = [
                    'tenant_id' => (int) $tid,
                    'anomaly_key' => 'export_report.denial_spike',
                    'category' => 'export_report',
                    'severity' => 'medium',
                    'reason_code' => 'export_report_denial_spike',
                    'summary_safe' => (int) $count.' export/report denial(s) in window.',
                    'metadata' => ['denial_count' => (int) $count],
                ];
            }
        }

        return $anomalies;
    }
}
