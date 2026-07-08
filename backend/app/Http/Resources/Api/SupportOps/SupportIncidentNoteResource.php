<?php

namespace App\Http\Resources\Api\SupportOps;

use App\Models\TenantSupportIncidentNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 35 — a redacted, tenant-isolated support incident note (SUP-R023).
 *
 * @property TenantSupportIncidentNote $resource
 */
class SupportIncidentNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toSafeArray();
    }
}
