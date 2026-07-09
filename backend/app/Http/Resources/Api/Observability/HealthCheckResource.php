<?php

namespace App\Http\Resources\Api\Observability;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 36 — safe application-health representation (OBS-R004/R020). Wraps the
 * already-safe aggregate produced by ObservabilityHealthService.
 */
class HealthCheckResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
