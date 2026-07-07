<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the array produced by ReceiptService. The service owns eligibility and
 * snapshot rules; this resource only shapes the envelope + foundation meta so
 * the response is `{ "data": {...}, "meta": {...} }`.
 */
class ReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return $data;
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
