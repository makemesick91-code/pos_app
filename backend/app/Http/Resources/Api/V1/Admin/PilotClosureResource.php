<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\PilotClosureRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PilotClosureRun
 *
 * Sprint 18 — presents a pilot closure run. Summaries are aggregate only; no
 * secret is exposed.
 */
class PilotClosureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'closure_reference' => $this->closure_reference,
            'status' => $this->status,
            'decision' => $this->decision,
            'window_start' => $this->window_start,
            'window_end' => $this->window_end,
            'final_defect_summary' => $this->final_defect_summary,
            'accepted_risk_summary' => $this->accepted_risk_summary,
            'handover_readiness_summary' => $this->handover_readiness_summary,
            'checklist' => $this->checklist,
            'evidence_references' => $this->evidence_references,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
