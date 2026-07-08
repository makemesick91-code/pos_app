<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\TenantBillingPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 30 — redacted payment representation for platform admin. metadata was
 * already sanitized on write; no gateway payloads or credentials are ever stored.
 *
 * @mixin TenantBillingPayment
 */
class TenantBillingPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'invoice_id' => $this->invoice_id,
            'payment_reference' => $this->payment_reference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->method,
            'status' => $this->status,
            'received_at' => optional($this->received_at)->toIso8601String(),
            'source' => $this->source,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
