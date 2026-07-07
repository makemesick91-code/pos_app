<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 12 — the result of a demo-data seed or reset operation. Reports counts
 * and checklist flags only; never exposes secrets. The wrapped resource is a
 * plain array assembled by the controller.
 *
 * @property array<string, mixed> $resource
 */
class TenantDemoDataResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
