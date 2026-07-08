<?php

namespace App\Services\AndroidRuntime;

use App\Models\TenantAndroidSyncBatch;
use App\Models\TenantAndroidSyncItem;
use App\Models\TenantDeviceActivation;

/**
 * Sprint 34 — safe, redacted admin summaries for device activations, sync batches,
 * failures and conflicts (ADR-R020/R022). Returns aggregate counts only — never a
 * token hash, fingerprint, raw payload or PII.
 */
class AndroidRuntimeSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function deviceSummary(?int $tenantId = null): array
    {
        $query = TenantDeviceActivation::query();
        if ($tenantId !== null) {
            $query->forTenant($tenantId);
        }

        $byStatus = (clone $query)
            ->selectRaw('activation_status, COUNT(*) as c')
            ->groupBy('activation_status')
            ->pluck('c', 'activation_status')
            ->toArray();

        return [
            'total' => (clone $query)->count(),
            'by_status' => $byStatus,
            'recent' => (clone $query)->orderByDesc('id')->limit(10)->get()
                ->map(fn (TenantDeviceActivation $a) => $a->toSafeArray())->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function syncSummary(?int $tenantId = null): array
    {
        $batchQuery = TenantAndroidSyncBatch::query();
        $itemQuery = TenantAndroidSyncItem::query();
        if ($tenantId !== null) {
            $batchQuery->forTenant($tenantId);
            $itemQuery->forTenant($tenantId);
        }

        return [
            'batches' => [
                'total' => (clone $batchQuery)->count(),
                'by_status' => (clone $batchQuery)
                    ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->toArray(),
                'accepted_items' => (int) (clone $batchQuery)->sum('accepted_count'),
                'duplicate_items' => (int) (clone $batchQuery)->sum('duplicate_count'),
                'conflict_items' => (int) (clone $batchQuery)->sum('conflict_count'),
                'failed_items' => (int) (clone $batchQuery)->sum('failed_count'),
            ],
            'items_by_status' => (clone $itemQuery)
                ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->toArray(),
            'conflicts_by_code' => (clone $itemQuery)
                ->whereNotNull('conflict_code')
                ->selectRaw('conflict_code, COUNT(*) as c')->groupBy('conflict_code')->pluck('c', 'conflict_code')->toArray(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentConflicts(?int $tenantId = null, int $limit = 25): array
    {
        $query = TenantAndroidSyncItem::query()->whereNotNull('conflict_code');
        if ($tenantId !== null) {
            $query->forTenant($tenantId);
        }

        return $query->orderByDesc('id')->limit($limit)->get()
            ->map(fn (TenantAndroidSyncItem $i) => $i->toSafeArray())->all();
    }
}
