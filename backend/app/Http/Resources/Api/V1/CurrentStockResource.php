<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single current-stock row: the derived (ledger-summed) stock for one product
 * in a store context. Backed by the plain array shape produced by
 * StockCalculator, not an Eloquent model.
 *
 * @property array<string, mixed> $resource
 */
class CurrentStockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->resource['product_id'],
            'sku' => $this->resource['sku'] ?? null,
            'barcode' => $this->resource['barcode'] ?? null,
            'name' => $this->resource['name'] ?? null,
            'unit' => $this->resource['unit'] ?? null,
            'is_stock_tracked' => (bool) ($this->resource['is_stock_tracked'] ?? false),
            'current_stock' => $this->resource['current_stock'] ?? '0.00',
        ];
    }
}
