<?php

namespace App\Http\Resources\Api\V1;

use App\Services\Subscriptions\SubscriptionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Presents the backend-computed subscription status for a tenant (Sprint 10),
 * including the plan limits and the active device count. The allowed/blocked
 * decision here is authoritative — Android must not trust any client-side state.
 *
 * @property SubscriptionStatus $resource
 */
class SubscriptionStatusResource extends JsonResource
{
    public function __construct(
        SubscriptionStatus $status,
        private readonly int $activeDeviceCount,
    ) {
        parent::__construct($status);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SubscriptionStatus $status */
        $status = $this->resource;
        $plan = $status->plan;
        $subscription = $status->subscription;

        return [
            'allowed' => $status->allowed,
            'status' => $status->status,
            'reason' => $status->reason,
            'plan' => $plan === null ? null : [
                'code' => $plan->code,
                'name' => $plan->name,
                'max_devices' => (int) $plan->max_devices,
                'max_stores' => (int) $plan->max_stores,
                'max_products' => $plan->max_products,
            ],
            'subscription' => $subscription === null ? null : [
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
                'trial_ends_at' => $subscription->trial_ends_at,
                'grace_ends_at' => $subscription->grace_ends_at,
            ],
            'devices' => [
                'active_count' => $this->activeDeviceCount,
                'max_devices' => $plan === null ? null : (int) $plan->max_devices,
            ],
        ];
    }
}
