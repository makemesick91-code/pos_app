<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\ProductionOperationRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductionOperationRun
 *
 * Sprint 19 — presents a production operation run. Summaries are aggregate only;
 * no secrets are exposed.
 */
class ProductionOperationRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operation_reference' => $this->operation_reference,
            'status' => $this->status,
            'decision' => $this->decision,
            'window_start' => $this->window_start,
            'window_end' => $this->window_end,
            'health_signals' => $this->health_signals,
            'incident_summary' => $this->incident_summary,
            'backup_restore_summary' => $this->backup_restore_summary,
            'support_sla_summary' => $this->support_sla_summary,
            'maintenance_summary' => $this->maintenance_summary,
            'release_rollback_summary' => $this->release_rollback_summary,
            'evidence_references' => $this->evidence_references,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
