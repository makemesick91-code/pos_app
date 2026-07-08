<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SalesLeadActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SalesLeadActivity
 *
 * Sprint 22 — presents a sales lead activity. Manual note only; no message is
 * ever sent. No secrets are exposed.
 */
class SalesLeadActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'activity_reference' => $this->activity_reference,
            'sales_lead_id' => $this->sales_lead_id,
            'actor_user_id' => $this->actor_user_id,
            'activity_type' => $this->activity_type,
            'status' => $this->status,
            'summary' => $this->summary,
            'notes' => $this->notes,
            'scheduled_at' => $this->scheduled_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
