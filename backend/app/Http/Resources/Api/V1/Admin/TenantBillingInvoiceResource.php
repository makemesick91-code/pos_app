<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\TenantBillingInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 30 — redacted invoice representation for platform admin. Exposes the
 * governance-relevant fields only; metadata was already sanitized on write.
 *
 * @mixin TenantBillingInvoice
 */
class TenantBillingInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'plan_key' => $this->plan_key,
            'invoice_number' => $this->invoice_number,
            'period_key' => $this->period_key,
            'period_start' => optional($this->period_start)->toIso8601String(),
            'period_end' => optional($this->period_end)->toIso8601String(),
            'issued_at' => optional($this->issued_at)->toIso8601String(),
            'due_at' => optional($this->due_at)->toIso8601String(),
            'currency' => $this->currency,
            'subtotal_amount' => $this->subtotal_amount,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'collected_amount' => $this->collectedAmount(),
            'outstanding_amount' => $this->outstandingAmount(),
            'status' => $this->status,
            'collection_state' => $this->collection_state,
            'source' => $this->source,
            'metadata' => $this->metadata,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
