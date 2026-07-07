<?php

namespace App\Services\Reports;

use App\Models\InventoryMovement;

/**
 * Summarizes inventory movements grouped by movement_type for a
 * tenant/store/date range (Sprint 9). The summary is always derived from the
 * `inventory_movements` ledger (never a mutable product stock column), using the
 * backend-computed `signed_qty`. No stock valuation is performed — this is a
 * simple movement count/quantity roll-up only.
 */
class InventoryMovementSummaryService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function summary(
        int $tenantId,
        ?int $storeId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $query = InventoryMovement::query()->forTenant($tenantId);

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        if ($dateFrom !== null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $rows = $query
            ->selectRaw('movement_type, COUNT(*) as movement_count, COALESCE(SUM(qty), 0) as qty_total, COALESCE(SUM(signed_qty), 0) as signed_qty_total')
            ->groupBy('movement_type')
            ->orderBy('movement_type')
            ->get();

        return $rows->map(fn ($row) => [
            'movement_type' => $row->movement_type,
            'movement_count' => (int) $row->movement_count,
            'qty_total' => number_format((float) $row->qty_total, 2, '.', ''),
            'signed_qty_total' => number_format((float) $row->signed_qty_total, 2, '.', ''),
        ])->all();
    }

    /**
     * Total SALE_OUT quantity (positive magnitude) within the range, for the
     * daily closing snapshot.
     */
    public function saleOutQty(
        int $tenantId,
        ?int $storeId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): string {
        foreach ($this->summary($tenantId, $storeId, $dateFrom, $dateTo) as $row) {
            if ($row['movement_type'] === InventoryMovement::TYPE_SALE_OUT) {
                return $row['qty_total'];
            }
        }

        return '0.00';
    }
}
