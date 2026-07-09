<?php

namespace App\Http\Resources\Api\Observability;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 36 — safe scheduler-health representation (OBS-R011). Command names +
 * freshness only; failure reasons are redacted upstream.
 */
class SchedulerHealthResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
