<?php

namespace App\Services\Subscriptions;

use App\Models\RegisteredDevice;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Support\Carbon;

/**
 * Resolves the authoritative allowed/blocked decision for a tenant's
 * subscription (Sprint 10). The persisted status column is only a hint: the
 * effective decision is always recomputed here from the date columns so a
 * lapsed trial/active window blocks even when the row still reads ACTIVE. The
 * client never supplies this decision. See Sprint 10 evidence.
 */
class SubscriptionStatusService
{
    public function resolve(Tenant $tenant): SubscriptionStatus
    {
        $subscription = $tenant->currentSubscription();

        if ($subscription === null) {
            return new SubscriptionStatus(
                allowed: false,
                status: TenantSubscription::STATUS_EXPIRED,
                reason: 'No active subscription for this tenant.',
                plan: null,
                subscription: null,
            );
        }

        $subscription->loadMissing('plan');
        $now = Carbon::now();

        [$allowed, $effectiveStatus, $reason] = $this->decide($subscription, $now);

        return new SubscriptionStatus(
            allowed: $allowed,
            status: $effectiveStatus,
            reason: $reason,
            plan: $subscription->plan,
            subscription: $subscription,
        );
    }

    /**
     * @return array{0: bool, 1: string, 2: ?string}
     */
    private function decide(TenantSubscription $subscription, Carbon $now): array
    {
        switch ($subscription->status) {
            case TenantSubscription::STATUS_CANCELLED:
                return [false, TenantSubscription::STATUS_CANCELLED, 'Subscription has been cancelled.'];

            case TenantSubscription::STATUS_SUSPENDED:
                return [false, TenantSubscription::STATUS_SUSPENDED, 'Subscription is suspended.'];

            case TenantSubscription::STATUS_EXPIRED:
                return [false, TenantSubscription::STATUS_EXPIRED, 'Subscription has expired.'];

            case TenantSubscription::STATUS_TRIAL:
                if ($subscription->trial_ends_at !== null && $now->greaterThan($subscription->trial_ends_at)) {
                    return [false, TenantSubscription::STATUS_EXPIRED, 'Trial period has ended.'];
                }

                return [true, TenantSubscription::STATUS_TRIAL, null];

            case TenantSubscription::STATUS_GRACE:
                if ($subscription->grace_ends_at !== null && $now->greaterThan($subscription->grace_ends_at)) {
                    return [false, TenantSubscription::STATUS_EXPIRED, 'Grace period has ended.'];
                }

                return [true, TenantSubscription::STATUS_GRACE, null];

            case TenantSubscription::STATUS_ACTIVE:
                if ($subscription->ends_at !== null && $now->greaterThan($subscription->ends_at)) {
                    // Past ends_at: allow only if still within an open grace window.
                    if ($subscription->grace_ends_at !== null && $now->lessThanOrEqualTo($subscription->grace_ends_at)) {
                        return [true, TenantSubscription::STATUS_GRACE, null];
                    }

                    return [false, TenantSubscription::STATUS_EXPIRED, 'Subscription has expired.'];
                }

                return [true, TenantSubscription::STATUS_ACTIVE, null];

            default:
                return [false, TenantSubscription::STATUS_EXPIRED, 'Unknown subscription status.'];
        }
    }

    public function activeDeviceCount(Tenant $tenant): int
    {
        return $tenant->registeredDevices()
            ->where('status', RegisteredDevice::STATUS_ACTIVE)
            ->count();
    }
}
