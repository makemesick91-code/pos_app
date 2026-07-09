<?php

namespace App\Http\Resources\Api\Observability;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 36 — safe failed-job diagnostic representation (OBS-R009). Redacted job
 * labels + counts only; never the raw payload, exception message, or stack trace.
 */
class FailedJobDiagnosticResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
