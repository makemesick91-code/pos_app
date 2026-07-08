<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SalesLeadAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SalesLeadAssignment
 *
 * Sprint 22 — presents a sales lead assignment history row. No secrets exposed.
 */
class SalesLeadAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_reference' => $this->assignment_reference,
            'sales_lead_id' => $this->sales_lead_id,
            'assigned_to_user_id' => $this->assigned_to_user_id,
            'assigned_by_user_id' => $this->assigned_by_user_id,
            'status' => $this->status,
            'assigned_at' => $this->assigned_at,
            'unassigned_at' => $this->unassigned_at,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
