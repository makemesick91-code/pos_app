<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\ProductionHandoverPackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductionHandoverPackage
 *
 * Sprint 18 — presents a production handover package. candidate_commit/tag are
 * references only; no secret or deployment credential is exposed.
 */
class ProductionHandoverPackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'handover_reference' => $this->handover_reference,
            'pilot_closure_run_id' => $this->pilot_closure_run_id,
            'status' => $this->status,
            'decision' => $this->decision,
            'candidate_commit' => $this->candidate_commit,
            'candidate_tag' => $this->candidate_tag,
            'production_readiness_summary' => $this->production_readiness_summary,
            'operator_handover_summary' => $this->operator_handover_summary,
            'admin_handover_summary' => $this->admin_handover_summary,
            'support_sla_summary' => $this->support_sla_summary,
            'backup_restore_summary' => $this->backup_restore_summary,
            'ownership_matrix' => $this->ownership_matrix,
            'checklist' => $this->checklist,
            'evidence_references' => $this->evidence_references,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'signoffs' => ProductionHandoverSignoffResource::collection($this->whenLoaded('signoffs')),
        ];
    }
}
