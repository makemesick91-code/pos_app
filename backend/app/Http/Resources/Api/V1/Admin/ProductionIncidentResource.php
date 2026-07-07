<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\ProductionIncident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductionIncident
 *
 * Sprint 19 — presents a production incident. The original severity is always
 * surfaced (accepted risk never hides it). No secrets are exposed; the service
 * sanitises stored free-text/metadata before persistence.
 */
class ProductionIncidentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'incident_reference' => $this->incident_reference,
            'tenant_id' => $this->tenant_id,
            'store_id' => $this->store_id,
            'reported_by' => $this->reported_by,
            'assigned_to' => $this->assigned_to,
            'area' => $this->area,
            'severity' => $this->severity,
            'status' => $this->status,
            'impact' => $this->impact,
            'title' => $this->title,
            'description' => $this->description,
            'detected_at' => $this->detected_at,
            'started_at' => $this->started_at,
            'resolved_at' => $this->resolved_at,
            'closed_at' => $this->closed_at,
            'sla_due_at' => $this->sla_due_at,
            'sla_breached_at' => $this->sla_breached_at,
            'accepted_risk' => [
                'at' => $this->accepted_risk_at,
                'by' => $this->accepted_risk_by,
                'reason' => $this->accepted_risk_reason,
                'expires_at' => $this->accepted_risk_expires_at,
                'valid' => $this->hasValidAcceptedRisk(),
            ],
            'resolution_summary' => $this->resolution_summary,
            'evidence_reference' => $this->evidence_reference,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
