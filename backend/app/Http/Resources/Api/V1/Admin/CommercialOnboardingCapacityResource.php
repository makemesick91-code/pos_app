<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 20 — presents the onboarding capacity report produced by
 * OnboardingCapacityService. Aggregate placeholders only; no real customer data.
 */
class CommercialOnboardingCapacityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
