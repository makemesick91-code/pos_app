<?php

namespace App\Http\Resources\Api\SupportOps;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 35 — read-only device/sync/cashier runtime summary (SUP-R022). Passthrough.
 */
class SupportAndroidRuntimeSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
