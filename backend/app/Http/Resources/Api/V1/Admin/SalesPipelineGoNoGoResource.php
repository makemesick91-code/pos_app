<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 22 — presents the sales pipeline GO/WATCH/NO-GO report produced by
 * SalesPipelineGoNoGoService. Aggregate, evidence-backed, and secret-safe.
 */
class SalesPipelineGoNoGoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
