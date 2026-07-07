<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 11 — presents a subscription plan for admin management. No secrets.
 *
 * @mixin SubscriptionPlan
 */
class AdminSubscriptionPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'price_monthly' => $this->price_monthly,
            'max_stores' => (int) $this->max_stores,
            'max_devices' => (int) $this->max_devices,
            'max_products' => $this->max_products,
            'features' => $this->features,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
