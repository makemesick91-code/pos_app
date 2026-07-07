<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 17 — presents the defect burn-down summary (decision + counts by
 * severity/status/area + SLA/accepted-risk/verification counts). Wraps the plain
 * array produced by DefectBurnDownService::summary().
 *
 * @property array<string,mixed> $resource
 */
class PilotDefectBurnDownResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string,mixed> $summary */
        $summary = $this->resource;

        return [
            'decision' => $summary['decision'] ?? null,
            'counts' => $summary['counts'] ?? [],
            'by_severity' => $summary['by_severity'] ?? [],
            'by_status' => $summary['by_status'] ?? [],
            'by_area' => $summary['by_area'] ?? [],
        ];
    }
}
