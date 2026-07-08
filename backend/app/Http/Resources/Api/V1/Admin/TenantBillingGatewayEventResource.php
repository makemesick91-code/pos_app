<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\TenantBillingGatewayEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 31 — redacted gateway event representation. Exposes verification/routing
 * outcome only. The raw signature is never present — only the boolean verdict and
 * a truncated `signature_hash` fingerprint (PGW-R011/R016).
 *
 * @mixin TenantBillingGatewayEvent
 */
class TenantBillingGatewayEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'event_type' => $this->event_type,
            'provider_event_id' => $this->provider_event_id,
            'provider_reference' => $this->provider_reference,
            'payment_intent_id' => $this->payment_intent_id,
            'invoice_id' => $this->invoice_id,
            'signature_verified' => $this->signature_verified,
            'signature_hash' => $this->signature_hash,
            'status' => $this->status,
            'normalized_status' => $this->normalized_status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'failure_reason' => $this->failure_reason,
            'occurred_at' => $this->occurred_at,
            'processed_at' => $this->processed_at,
            'created_at' => $this->created_at,
        ];
    }
}
