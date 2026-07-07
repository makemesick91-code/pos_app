<?php

namespace App\Http\Resources\Api\V1;

use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaleItem
 */
class SaleItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'product_barcode' => $this->product_barcode,
            'unit' => $this->unit,
            'qty' => $this->qty,
            'unit_price' => $this->unit_price,
            'discount' => $this->discount,
            'subtotal' => $this->subtotal,
        ];
    }
}
