<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SaasBillingCollectionActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaasBillingCollectionActivity
 *
 * Sprint 23 — presents a SaaS billing collection activity. No secrets are exposed.
 */
class BillingCollectionActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'activity_reference' => $this->activity_reference,
            'billing_account_id' => $this->billing_account_id,
            'invoice_id' => $this->invoice_id,
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
