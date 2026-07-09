<?php

namespace App\Http\Resources\Api\Observability;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 36 — safe operational-metrics representation (OBS-R023). Aggregate counts
 * only; never a raw payload or PII.
 */
class ObservabilityMetricResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
