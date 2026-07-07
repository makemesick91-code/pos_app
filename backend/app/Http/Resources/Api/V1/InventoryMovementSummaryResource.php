<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single inventory movement summary row grouped by movement_type (Sprint 9).
 * Derived from the inventory_movements ledger; no stock valuation is exposed.
 *
 * @property array<string, mixed> $resource
 */
class InventoryMovementSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'movement_type' => $this->resource['movement_type'],
            'movement_count' => $this->resource['movement_count'],
            'qty_total' => $this->resource['qty_total'],
            'signed_qty_total' => $this->resource['signed_qty_total'],
        ];
    }
}
