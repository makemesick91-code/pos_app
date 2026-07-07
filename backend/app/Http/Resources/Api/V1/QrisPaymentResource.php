<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 *
 * Android-safe view of a QRIS payment: the QR payload/text and status, but never
 * raw_response or any provider credential. Used for both the create and status
 * endpoints; sale_payment_status is included when the sale relation is loaded.
 */
class QrisPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'method' => $this->method,
            'provider' => $this->provider,
            'status' => $this->status,
            'amount' => $this->amount,
            'provider_reference' => $this->provider_reference,
            'qr_payload' => $this->qr_payload,
            'qr_image_url' => $this->qr_image_url,
            'payment_url' => $this->payment_url,
            'expired_at' => $this->expired_at,
            'paid_at' => $this->paid_at,
            'sale_payment_status' => $this->whenLoaded('sale', fn () => $this->sale->payment_status),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
            ],
        ];
    }
}
