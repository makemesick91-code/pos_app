<?php

namespace App\Services\SupportOperations;

use App\Models\TenantAndroidSyncBatch;
use App\Models\TenantAndroidSyncItem;
use App\Models\TenantDeviceActivation;

/**
 * Sprint 35 — read-only device / sync / cashier runtime viewer (SUP-R022).
 *
 * Reads the Sprint 34 tenant_device_activations / tenant_android_sync_batches /
 * tenant_android_sync_items safely (via each model's toSafeArray) and inspects
 * sync failures. NEVER mutates a device, batch or item; no raw sync payload,
 * token hash or fingerprint is returned.
 */
class SupportAndroidRuntimeViewerService
{
    public function summary(int $tenantId, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        $devices = TenantDeviceActivation::query()
            ->forTenant($tenantId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $batches = TenantAndroidSyncBatch::query()
            ->forTenant($tenantId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $deviceByStatus = [];
        foreach ($devices as $device) {
            $deviceByStatus[$device->activation_status] = ($deviceByStatus[$device->activation_status] ?? 0) + 1;
        }

        $batchByStatus = [];
        foreach ($batches as $batch) {
            $batchByStatus[$batch->status] = ($batchByStatus[$batch->status] ?? 0) + 1;
        }

        return [
            'read_only' => true,
            'device_count' => $devices->count(),
            'devices_by_status' => $deviceByStatus,
            'revoked_device_count' => (int) ($deviceByStatus[TenantDeviceActivation::STATUS_REVOKED] ?? 0),
            'sync_batch_count' => $batches->count(),
            'sync_batches_by_status' => $batchByStatus,
            'devices' => $devices->take(10)->map(fn (TenantDeviceActivation $d) => $d->toSafeArray())->all(),
            'sync_batches' => $batches->take(10)->map(fn (TenantAndroidSyncBatch $b) => $b->toSafeArray())->all(),
        ];
    }

    /**
     * Sync failure inspection sourced from the Sprint 34 sync ledgers (SUP-R022).
     */
    public function syncFailures(int $tenantId, int $limit = 100): array
    {
        $batchStatuses = (array) config('support_operations_governance.sync_inspection.batch_failure_statuses', []);
        $itemStatuses = (array) config('support_operations_governance.sync_inspection.item_failure_statuses', []);
        $limit = max(1, min($limit, 200));

        $failedBatches = TenantAndroidSyncBatch::query()
            ->forTenant($tenantId)
            ->whereIn('status', $batchStatuses)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $failedItems = TenantAndroidSyncItem::query()
            ->forTenant($tenantId)
            ->whereIn('status', $itemStatuses)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return [
            'read_only' => true,
            'failed_batch_count' => $failedBatches->count(),
            'failed_item_count' => $failedItems->count(),
            'failed_batches' => $failedBatches->take(20)->map(fn (TenantAndroidSyncBatch $b) => $b->toSafeArray())->all(),
            'failed_items' => $failedItems->take(20)->map(fn (TenantAndroidSyncItem $i) => $i->toSafeArray())->all(),
        ];
    }
}
