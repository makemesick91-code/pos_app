<?php

namespace App\Http\Resources\Api\SupportOps;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 35 — read-only onboarding/provisioning summary (SUP-R011). Passthrough.
 */
class SupportOnboardingSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
