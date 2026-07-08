<?php

namespace App\Http\Resources\Api\SupportOps;

use App\Models\TenantSupportIncident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 35 — a redacted, PII/secret-free support incident (SUP-R007/R023).
 *
 * @property TenantSupportIncident $resource
 */
class SupportIncidentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toSafeArray();
    }
}
