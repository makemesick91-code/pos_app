<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 17 — presents the aggregated stabilization report (decision + signals +
 * burn-down + SLA + gate references). Wraps the plain array produced by
 * PilotStabilizationReportService::evaluate(). No secrets are exposed.
 *
 * @property array<string,mixed> $resource
 */
class PilotStabilizationReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string,mixed> $report */
        $report = $this->resource;

        return [
            'decision' => $report['decision'] ?? null,
            'signals' => $report['signals'] ?? [],
            'burndown' => $report['burndown'] ?? [],
            'sla' => $report['sla'] ?? [],
            'gates' => $report['gates'] ?? [],
        ];
    }
}
