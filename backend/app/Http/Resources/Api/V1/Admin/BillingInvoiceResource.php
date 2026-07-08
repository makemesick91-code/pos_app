<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SaasBillingInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaasBillingInvoice
 *
 * Sprint 23 — presents a SaaS billing invoice. Totals are server-calculated. No
 * secrets are exposed.
 */
class BillingInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_reference' => $this->invoice_reference,
            'invoice_number' => $this->invoice_number,
            'billing_account_id' => $this->billing_account_id,
            'tenant_id' => $this->tenant_id,
            'tenant_subscription_id' => $this->tenant_subscription_id,
            'billing_cycle_id' => $this->billing_cycle_id,
            'status' => $this->status,
            'issue_date' => $this->issue_date,
            'due_date' => $this->due_date,
            'currency' => $this->currency,
            'subtotal_amount' => $this->subtotal_amount,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'issued_at' => $this->issued_at,
            'voided_at' => $this->voided_at,
            'void_reason' => $this->void_reason,
            'notes' => $this->notes,
            'lines' => BillingInvoiceLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
