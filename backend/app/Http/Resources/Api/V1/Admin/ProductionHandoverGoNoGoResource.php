<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 18 — presents the aggregated production handover GO/WATCH/NO_GO report
 * (decision + signals + sub-reviews + gate references). Wraps the plain array
 * produced by ProductionHandoverGoNoGoService::evaluate(). No secrets exposed.
 *
 * @property array<string,mixed> $resource
 */
class ProductionHandoverGoNoGoResource extends JsonResource
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
            'final_defect_review' => $report['final_defect_review'] ?? [],
            'accepted_risk_review' => $report['accepted_risk_review'] ?? [],
            'signoff_summary' => $report['signoff_summary'] ?? [],
            'closure' => $report['closure'] ?? null,
            'package' => $report['package'] ?? null,
            'gates' => $report['gates'] ?? [],
        ];
    }
}
