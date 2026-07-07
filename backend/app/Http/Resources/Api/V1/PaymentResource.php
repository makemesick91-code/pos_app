<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 *
 * Never exposes raw_response or provider credentials — only the safe, tenant-safe
 * summary of a payment.
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method,
            'amount' => $this->amount,
            'status' => $this->status,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'paid_at' => $this->paid_at,
        ];
    }
}
