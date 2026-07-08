<?php

namespace App\Http\Resources\Api\SupportOps;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 35 — a deterministic, redacted diagnostic timeline (SUP-R020). Passthrough.
 */
class SupportTimelineEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
