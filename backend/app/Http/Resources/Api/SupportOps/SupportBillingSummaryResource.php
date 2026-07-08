<?php

namespace App\Http\Resources\Api\SupportOps;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 35 — read-only billing/invoice/collection summary (SUP-R008). Passthrough.
 */
class SupportBillingSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
