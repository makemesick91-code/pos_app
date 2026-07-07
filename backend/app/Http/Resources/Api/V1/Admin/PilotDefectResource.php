<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\PilotDefect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PilotDefect
 *
 * Sprint 17 — presents a pilot defect. The original severity and blocking flag
 * are always surfaced (accepted risk never hides them). No secrets are exposed;
 * the service sanitises stored free-text/metadata before persistence.
 */
class PilotDefectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'defect_reference' => $this->defect_reference,
            'tenant_id' => $this->tenant_id,
            'store_id' => $this->store_id,
            'reported_by' => $this->reported_by,
            'assigned_to' => $this->assigned_to,
            'area' => $this->area,
            'severity' => $this->severity,
            'status' => $this->status,
            'blocking' => (bool) $this->blocking,
            'title' => $this->title,
            'description' => $this->description,
            'steps_to_reproduce' => $this->steps_to_reproduce,
            'expected_result' => $this->expected_result,
            'actual_result' => $this->actual_result,
            'environment' => $this->environment,
            'evidence_reference' => $this->evidence_reference,
            'sla_due_at' => $this->sla_due_at,
            'sla_breached_at' => $this->sla_breached_at,
            'accepted_risk' => [
                'at' => $this->accepted_risk_at,
                'by' => $this->accepted_risk_by,
                'reason' => $this->accepted_risk_reason,
                'expires_at' => $this->accepted_risk_expires_at,
                'valid' => $this->hasValidAcceptedRisk(),
            ],
            'fixed_at' => $this->fixed_at,
            'verified_at' => $this->verified_at,
            'verified_by' => $this->verified_by,
            'verification_result' => $this->verification_result,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'events' => PilotDefectEventResource::collection($this->whenLoaded('events')),
        ];
    }
}
