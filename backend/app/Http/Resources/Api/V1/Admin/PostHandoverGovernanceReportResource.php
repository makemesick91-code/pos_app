<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 19 — presents the post-handover production operations GO/WATCH/NO_GO
 * report (aggregated gates + governance signals). Wraps the service array; no
 * secrets are exposed.
 */
class PostHandoverGovernanceReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
