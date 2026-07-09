<?php

namespace App\Http\Resources\Api\Observability;

use App\Models\ObservabilityHealthSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 36 — safe health snapshot representation (OBS-R004/R020). Aggregate
 * metrics only; never raw payloads/PII.
 */
class HealthSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ObservabilityHealthSnapshot $snapshot */
        $snapshot = $this->resource;

        return [
            'id' => $snapshot->id,
            'scope_type' => $snapshot->scope_type,
            'tenant_id' => $snapshot->tenant_id,
            'status' => $snapshot->status,
            'reason_code' => $snapshot->reason_code,
            'summary_safe' => $snapshot->summary_safe,
            'metrics' => $snapshot->metrics_json,
            'checked_at' => optional($snapshot->checked_at)->toIso8601String(),
        ];
    }
}
