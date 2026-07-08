<?php

namespace App\Http\Resources\Api\SupportOps;

use App\Models\TenantSupportSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 35 — a support session (read-only context). Never exposes a raw
 * credential/token (SUP-R017/R019).
 *
 * @property TenantSupportSession $resource
 */
class SupportSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toSafeArray();
    }
}
