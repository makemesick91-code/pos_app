<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SubscriptionRenewalPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionRenewalPolicy
 *
 * Sprint 24 — presents a subscription renewal policy. No secrets are exposed.
 */
class SubscriptionRenewalPolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'policy_reference' => $this->policy_reference,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'renewal_window_days' => $this->renewal_window_days,
            'grace_period_days' => $this->grace_period_days,
            'dunning_start_days_before_expiry' => $this->dunning_start_days_before_expiry,
            'max_manual_dunning_notices' => $this->max_manual_dunning_notices,
            'requires_manual_approval' => $this->requires_manual_approval,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
