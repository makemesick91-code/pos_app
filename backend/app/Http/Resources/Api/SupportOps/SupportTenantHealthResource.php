<?php

namespace App\Http\Resources\Api\SupportOps;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 35 — tenant health overview (already a redacted, aggregate-safe array
 * from SupportTenantHealthService). Passthrough; no PII/secrets (SUP-R002/R007).
 */
class SupportTenantHealthResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
