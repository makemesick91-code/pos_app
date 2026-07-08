<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SaasBillingInvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaasBillingInvoiceLine
 *
 * Sprint 23 — presents a SaaS billing invoice line. line_total is server-computed.
 * No secrets are exposed.
 */
class BillingInvoiceLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'line_reference' => $this->line_reference,
            'item_type' => $this->item_type,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_amount' => $this->unit_amount,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'line_total' => $this->line_total,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
