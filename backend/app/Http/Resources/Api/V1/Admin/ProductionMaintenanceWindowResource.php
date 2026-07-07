<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\ProductionMaintenanceWindow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductionMaintenanceWindow
 *
 * Sprint 19 — presents a production maintenance window. No secrets are exposed;
 * a HIGH/CRITICAL window without a rollback plan is flagged via has_rollback_plan.
 */
class ProductionMaintenanceWindowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maintenance_reference' => $this->maintenance_reference,
            'status' => $this->status,
            'title' => $this->title,
            'description' => $this->description,
            'scheduled_start_at' => $this->scheduled_start_at,
            'scheduled_end_at' => $this->scheduled_end_at,
            'actual_start_at' => $this->actual_start_at,
            'actual_end_at' => $this->actual_end_at,
            'risk_level' => $this->risk_level,
            'owner_user_id' => $this->owner_user_id,
            'rollback_plan_reference' => $this->rollback_plan_reference,
            'has_rollback_plan' => $this->hasRollbackPlan(),
            'is_high_risk' => $this->isHighRisk(),
            'evidence_reference' => $this->evidence_reference,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
