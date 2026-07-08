<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\TenantBillingPaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 31 — redacted payment intent representation. Exposes governance-relevant
 * fields only; `metadata` is already sanitized at write time and no secret/
 * signature is present (PGW-R011/R016).
 *
 * @mixin TenantBillingPaymentIntent
 */
class TenantBillingPaymentIntentResource extends JsonResource
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
            'provider' => $this->provider,
            'channel' => $this->channel,
            'period_key' => $this->period_key,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'provider_reference' => $this->provider_reference,
            'expires_at' => $this->expires_at,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
    }
}
