<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 19 — presents the production operations health evaluation (secret-safe
 * aggregate signals + GO/WATCH/NO_GO decision). Wraps the service array.
 */
class ProductionOpsHealthResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
