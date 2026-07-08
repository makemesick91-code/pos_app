<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 26 — presents the read-only tenant plan governance summary (counts of
 * plans, registries, assignments, overrides). Secret-safe.
 */
class TenantPlanGovernanceSummaryResource extends JsonResource
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
}
