<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DailyClosing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Presents a daily closing snapshot (Sprint 9). All totals shown here were
 * computed by the backend report services at close time; the client never
 * supplied them.
 *
 * @mixin DailyClosing
 */
class DailyClosingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'store_id' => $this->store_id,
            'business_date' => $this->business_date?->toDateString(),
            'status' => $this->status,
            'sales_count' => $this->sales_count,
            'cancelled_sales_count' => $this->cancelled_sales_count,
            'cash_total' => $this->cash_total,
            'qris_total' => $this->qris_total,
            'gross_total' => $this->gross_total,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'grand_total' => $this->grand_total,
            'paid_total' => $this->paid_total,
            'change_total' => $this->change_total,
            'inventory_sale_out_qty' => $this->inventory_sale_out_qty,
            'closed_by' => $this->closed_by,
            'closed_at' => $this->closed_at,
            'notes' => $this->notes,
            'snapshot' => $this->snapshot,
        ];
    }
}
