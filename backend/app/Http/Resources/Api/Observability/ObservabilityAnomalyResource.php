<?php

namespace App\Http\Resources\Api\Observability;

use App\Models\ObservabilityAnomalyEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 36 — safe anomaly event representation (OBS-R004). Redacted by the model
 * toSafeArray(); never exposes raw metadata payloads.
 */
class ObservabilityAnomalyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ObservabilityAnomalyEvent $anomaly */
        $anomaly = $this->resource;

        return $anomaly->toSafeArray();
    }
}
