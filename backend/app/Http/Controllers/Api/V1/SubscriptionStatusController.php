<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SubscriptionStatusResource;
use App\Services\Subscriptions\SubscriptionStatusService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/subscription/status (Sprint 10).
 *
 * Returns the backend-computed subscription decision + plan limits + active
 * device count for the authenticated tenant. Reachable even when the
 * subscription is blocked (it is not wrapped by subscription.active) so Android
 * can render its blocked state. Does not require a registered device.
 */
class SubscriptionStatusController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SubscriptionStatusService $subscriptionStatus,
    ) {}

    public function show(): JsonResponse
    {
        $tenant = $this->context->tenant();

        $status = $this->subscriptionStatus->resolve($tenant);
        $activeCount = $this->subscriptionStatus->activeDeviceCount($tenant);

        return (new SubscriptionStatusResource($status, $activeCount))
            ->additional([
                'meta' => [
                    'tenant_id' => (int) $tenant->id,
                    'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
                ],
            ])
            ->response();
    }
}
