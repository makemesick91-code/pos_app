<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\TenantSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 11 — presents a tenant subscription row for admin management. Exposes
 * only status/date/plan fields; never metadata that could carry gateway
 * payloads or secrets.
 *
 * @mixin TenantSubscription
 */
class AdminTenantSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'subscription_plan_id' => $this->subscription_plan_id,
            'plan_code' => $this->whenLoaded('plan', fn () => $this->plan?->code),
            'status' => $this->status,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'trial_ends_at' => $this->trial_ends_at,
            'grace_ends_at' => $this->grace_ends_at,
            'cancelled_at' => $this->cancelled_at,
            'suspended_at' => $this->suspended_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
