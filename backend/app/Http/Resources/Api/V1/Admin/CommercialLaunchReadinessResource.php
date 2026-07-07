<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 20 — presents the commercial launch readiness report. The underlying
 * resource is the aggregate array produced by CommercialLaunchReadinessService;
 * it is aggregate and secret-safe by construction.
 */
class CommercialLaunchReadinessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
