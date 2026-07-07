<?php

namespace App\Http\Resources\Api\V1;

use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryMovement
 */
class InventoryMovementResource extends JsonResource
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
            'movement_type' => $this->movement_type,
            'qty' => $this->qty,
            'signed_qty' => $this->signed_qty,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'source' => $this->source,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
