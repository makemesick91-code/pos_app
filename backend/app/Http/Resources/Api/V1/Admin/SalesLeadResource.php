<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SalesLead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SalesLead
 *
 * Sprint 22 — presents a sales lead. No secrets are exposed. A lead is never a
 * tenant/user/subscription/device.
 */
class SalesLeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_reference' => $this->lead_reference,
            'lead_interest_submission_id' => $this->lead_interest_submission_id,
            'pipeline_stage_id' => $this->pipeline_stage_id,
            'status' => $this->status,
            'source' => $this->source,
            'business_name' => $this->business_name,
            'contact_name' => $this->contact_name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'business_type' => $this->business_type,
            'estimated_store_count' => $this->estimated_store_count,
            'estimated_device_count' => $this->estimated_device_count,
            'interest_package_code' => $this->interest_package_code,
            'qualification_score' => $this->qualification_score,
            'priority' => $this->priority,
            'assigned_to_user_id' => $this->assigned_to_user_id,
            'qualified_at' => $this->qualified_at,
            'lost_at' => $this->lost_at,
            'lost_reason' => $this->lost_reason,
            'ready_for_onboarding_at' => $this->ready_for_onboarding_at,
            'evidence_reference' => $this->evidence_reference,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
