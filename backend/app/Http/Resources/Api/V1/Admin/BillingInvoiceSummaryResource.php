<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 23 — presents the billing invoice summary array. No secrets are exposed.
 */
class BillingInvoiceSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
