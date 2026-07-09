<?php

namespace App\Http\Resources\Api\Observability;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 36 — safe tenant runtime probe representation (OBS-R012). Tenant-isolated
 * health status + safe dimensions from the Sprint 35 health service; no PII/secrets.
 */
class TenantRuntimeProbeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
