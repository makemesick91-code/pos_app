<?php

namespace App\Http\Resources\Api\SupportOps;

use App\Models\TenantSupportAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 35 — a redacted support action ledger row (SUP-R006/R007).
 *
 * @property TenantSupportAction $resource
 */
class SupportActionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toSafeArray();
    }
}
