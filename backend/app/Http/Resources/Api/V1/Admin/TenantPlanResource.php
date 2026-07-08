<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\TenantPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TenantPlan
 *
 * Sprint 26 — presents a plan from the tenant plan catalogue with its entitlement
 * flags and usage limits. No secrets.
 */
class TenantPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'billing_interval' => $this->billing_interval,
            'entitlements' => $this->whenLoaded('entitlements', fn () => $this->entitlements->map(fn ($e) => [
                'entitlement_key' => $e->entitlement_key,
                'enabled' => (bool) $e->enabled,
            ])->values()),
            'usage_limits' => $this->whenLoaded('usageLimits', fn () => $this->usageLimits->map(fn ($l) => [
                'limit_key' => $l->limit_key,
                'limit_value' => $l->unlimited ? null : $l->limit_value,
                'unlimited' => (bool) $l->unlimited,
                'period' => $l->period,
            ])->values()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
