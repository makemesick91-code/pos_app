<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 21 — presents the public website readiness report produced by
 * PublicWebsiteReadinessService. Aggregate, evidence-backed, and secret-safe.
 */
class PublicWebsiteReadinessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
