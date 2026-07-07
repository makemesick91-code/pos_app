<?php

namespace App\Services\Subscriptions;

use App\Models\SubscriptionPlan;
use App\Models\TenantSubscription;

/**
 * Immutable result of resolving a tenant's subscription (Sprint 10). Produced
 * only by SubscriptionStatusService — the allowed/blocked decision is always
 * backend-computed and never trusted from the client.
 */
class SubscriptionStatus
{
    public function __construct(
        public readonly bool $allowed,
        public readonly string $status,
        public readonly ?string $reason,
        public readonly ?SubscriptionPlan $plan,
        public readonly ?TenantSubscription $subscription,
    ) {}

    public const CODE_INACTIVE = 'SUBSCRIPTION_INACTIVE';
    public const CODE_NONE = 'SUBSCRIPTION_NONE';

    public function code(): string
    {
        return $this->subscription === null ? self::CODE_NONE : self::CODE_INACTIVE;
    }
}
