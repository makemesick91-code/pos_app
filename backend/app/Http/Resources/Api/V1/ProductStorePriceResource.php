<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProductStorePrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductStorePrice
 */
class ProductStorePriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'product_id' => $this->product_id,
            'selling_price' => $this->selling_price,
            'is_active' => $this->is_active,
            'updated_at' => $this->updated_at,
        ];
    }
}
