<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 22 — presents the sales activity summary produced by
 * SalesLeadActivityService. Manual follow-up only; aggregate and secret-safe.
 */
class SalesPipelineActivitySummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
