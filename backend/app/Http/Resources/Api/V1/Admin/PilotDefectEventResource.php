<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\PilotDefectEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PilotDefectEvent
 *
 * Sprint 17 — presents one immutable defect lifecycle event. Payloads are
 * sanitised before persistence, so no secrets are exposed here.
 */
class PilotDefectEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pilot_defect_id' => $this->pilot_defect_id,
            'actor_user_id' => $this->actor_user_id,
            'event_type' => $this->event_type,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'from_severity' => $this->from_severity,
            'to_severity' => $this->to_severity,
            'message' => $this->message,
            'payload' => $this->payload,
            'evidence_reference' => $this->evidence_reference,
            'created_at' => $this->created_at,
        ];
    }
}
