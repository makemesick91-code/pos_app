<?php

namespace App\Services\Observability;

use App\Models\TenantAndroidSyncBatch;
use App\Models\TenantAndroidSyncItem;
use App\Models\TenantDeviceActivation;
use Illuminate\Support\Carbon;

/**
 * Sprint 36 — Android sync anomaly detection (OBS-R013).
 *
 * READ-ONLY. Sources exclusively from the Sprint 34 sync ledgers
 * (tenant_android_sync_batches / _items) and device activations. Detects repeated
 * batch failures, high conflict rate, duplicate replay spikes and revoked-device
 * sync attempts. Emits safe anomaly descriptors; it NEVER mutates a batch, item,
 * or device. Threshold-driven (OBS-R029).
 */
class AndroidSyncAnomalyService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function detect(?int $tenantId = null): array
    {
        $t = (array) config('observability_governance.thresholds', []);
        $since = Carbon::now()->subHours((int) config('observability_governance.anomaly.default_lookback_hours', 168));

        $anomalies = [];

        $batchQuery = TenantAndroidSyncBatch::query()->where('created_at', '>=', $since);
        if ($tenantId !== null) {
            $batchQuery->where('tenant_id', $tenantId);
        }

        $failedByTenant = (clone $batchQuery)
            ->whereIn('status', [
                TenantAndroidSyncBatch::STATUS_FAILED,
                TenantAndroidSyncBatch::STATUS_PARTIAL_FAILED,
                TenantAndroidSyncBatch::STATUS_REJECTED,
            ])
            ->selectRaw('tenant_id, count(*) as c')
            ->groupBy('tenant_id')
            ->pluck('c', 'tenant_id');

        foreach ($failedByTenant as $tid => $count) {
            $count = (int) $count;
            $severity = $count >= (int) ($t['sync_failed_batch_degraded'] ?? 5)
                ? 'high'
                : ($count >= (int) ($t['sync_failed_batch_watch'] ?? 1) ? 'medium' : null);
            if ($severity === null) {
                continue;
            }
            $anomalies[] = $this->descriptor((int) $tid, 'android_sync.failed_batches', $severity,
                'repeated_sync_batch_failures', $count.' failed/rejected sync batch(es) in window.', ['failed_batch_count' => $count]);
        }

        // Conflict + duplicate items per tenant.
        $itemQuery = TenantAndroidSyncItem::query()->where('created_at', '>=', $since);
        if ($tenantId !== null) {
            $itemQuery->where('tenant_id', $tenantId);
        }
        $itemStats = (clone $itemQuery)
            ->selectRaw('tenant_id, count(*) as total, '
                .'sum(case when status = ? then 1 else 0 end) as conflicts, '
                .'sum(case when status = ? then 1 else 0 end) as duplicates', [
                    TenantAndroidSyncItem::STATUS_CONFLICT,
                    TenantAndroidSyncItem::STATUS_DUPLICATE,
                ])
            ->groupBy('tenant_id')
            ->get();

        foreach ($itemStats as $row) {
            $tid = (int) $row->tenant_id;
            $total = max(1, (int) $row->total);
            $conflicts = (int) $row->conflicts;
            $duplicates = (int) $row->duplicates;

            $conflictRate = $conflicts / $total;
            if ($conflictRate >= (float) ($t['sync_conflict_rate_watch'] ?? 0.1) && $conflicts > 0) {
                $anomalies[] = $this->descriptor($tid, 'android_sync.high_conflict_rate', 'medium',
                    'high_sync_conflict_rate', 'Sync conflict rate above threshold.',
                    ['conflict_count' => $conflicts, 'item_count' => $total]);
            }

            if ($duplicates >= (int) ($t['sync_duplicate_spike'] ?? 20)) {
                $anomalies[] = $this->descriptor($tid, 'android_sync.duplicate_spike', 'medium',
                    'duplicate_replay_spike', 'Duplicate sync item replay spike detected.',
                    ['duplicate_count' => $duplicates]);
            }
        }

        // Revoked-device sync attempts: a tenant with ≥1 revoked device AND
        // failed/rejected sync batches in-window (a proxy sourced from the Sprint
        // 34 ledgers; never inspects credentials).
        $revokedByTenant = TenantDeviceActivation::query()
            ->where('activation_status', TenantDeviceActivation::STATUS_REVOKED)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('tenant_id, count(*) as c')
            ->groupBy('tenant_id')
            ->pluck('c', 'tenant_id');

        foreach ($revokedByTenant as $tid => $count) {
            $tid = (int) $tid;
            if ((int) ($failedByTenant[$tid] ?? 0) >= (int) ($t['revoked_device_attempt_watch'] ?? 1)) {
                $anomalies[] = $this->descriptor($tid, 'android_sync.revoked_device_attempt', 'high',
                    'revoked_device_sync_attempt', 'Sync failures observed for a tenant with a revoked device.',
                    ['revoked_device_count' => (int) $count]);
            }
        }

        return $anomalies;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function descriptor(int $tenantId, string $key, string $severity, string $reason, string $summary, array $metadata): array
    {
        return [
            'tenant_id' => $tenantId,
            'anomaly_key' => $key,
            'category' => 'android_sync',
            'severity' => $severity,
            'reason_code' => $reason,
            'summary_safe' => $summary,
            'metadata' => $metadata,
        ];
    }
}
