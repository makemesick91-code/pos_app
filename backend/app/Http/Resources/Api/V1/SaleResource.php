<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sale
 */
class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'cashier_id' => $this->cashier_id,
            'invoice_number' => $this->invoice_number,
            'sale_date' => $this->sale_date,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'grand_total' => $this->grand_total,
            'paid_total' => $this->paid_total,
            'change_total' => $this->change_total,
            'payment_status' => $this->payment_status,
            'sync_status' => $this->sync_status,
            'source' => $this->source,
            'client_reference' => $this->client_reference,
            'client_created_at' => $this->client_created_at,
            'synced_at' => $this->synced_at,
            'notes' => $this->notes,
            'cancelled_at' => $this->cancelled_at,
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        $meta = [
            'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
        ];

        // Sprint 7: a single-sale response advertises whether it was an idempotent
        // replay of an already-stored offline submit. Collections omit this.
        if ($this->resource instanceof Sale) {
            $meta['idempotent_replay'] = (bool) $this->resource->idempotentReplay;
        }

        return ['meta' => $meta];
    }
}
