<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SubscriptionRenewalActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionRenewalActivity
 *
 * Sprint 24 — presents a subscription renewal activity. No secrets are exposed
 * and no real message is ever sent.
 */
class SubscriptionRenewalActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'activity_reference' => $this->activity_reference,
            'candidate_id' => $this->candidate_id,
            'tenant_id' => $this->tenant_id,
            'tenant_subscription_id' => $this->tenant_subscription_id,
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
