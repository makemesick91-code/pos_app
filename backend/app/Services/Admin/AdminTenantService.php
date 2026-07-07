<?php

namespace App\Services\Admin;

use App\Models\Tenant;
use App\Services\Subscriptions\SubscriptionStatusService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sprint 11 — read-only cross-tenant administration queries for platform admins.
 *
 * Surfaces tenant summaries and detail with subscription + device + store
 * counts. Never exposes secrets or raw payment gateway payloads. This service is
 * the only path by which a platform admin reads cross-tenant data.
 */
class AdminTenantService
{
    public function __construct(
        private readonly SubscriptionStatusService $subscriptionStatus,
    ) {}

    /**
     * @param  array{q?: string|null, status?: string|null, subscription_status?: string|null, limit?: int|null}  $filters
     * @return LengthAwarePaginator<Tenant>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $limit = (int) ($filters['limit'] ?? 50);
        $limit = max(1, min($limit, 100));

        $query = Tenant::query()
            ->withCount([
                'stores',
                'registeredDevices as devices_active_count' => function (Builder $q): void {
                    $q->where('status', 'ACTIVE');
                },
            ])
            ->with(['tenantSubscriptions' => fn ($q) => $q->orderByDesc('id')->with('plan')])
            ->orderByDesc('id');

        if (! empty($filters['q'])) {
            $q = (string) $filters['q'];
            $query->where(function (Builder $inner) use ($q): void {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('owner_name', 'like', "%{$q}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        $paginator = $query->paginate($limit);

        // subscription_status is an authoritative (recomputed) filter, applied
        // after load since it is not a stored column.
        if (! empty($filters['subscription_status'])) {
            $wanted = strtoupper((string) $filters['subscription_status']);
            $filtered = $paginator->getCollection()->filter(
                fn (Tenant $tenant) => $this->subscriptionStatus->resolve($tenant)->status === $wanted,
            )->values();
            $paginator->setCollection($filtered);
        }

        return $paginator;
    }

    /**
     * Authoritative subscription summary for a tenant (recomputed, never trusted
     * from a stored status column).
     *
     * @return array<string, mixed>
     */
    public function subscriptionSummary(Tenant $tenant): array
    {
        $status = $this->subscriptionStatus->resolve($tenant);
        $plan = $status->plan;
        $subscription = $status->subscription;

        return [
            'allowed' => $status->allowed,
            'status' => $status->status,
            'plan_code' => $plan?->code,
            'plan_name' => $plan?->name,
            'starts_at' => $subscription?->starts_at,
            'ends_at' => $subscription?->ends_at,
            'trial_ends_at' => $subscription?->trial_ends_at,
            'grace_ends_at' => $subscription?->grace_ends_at,
            'max_devices' => $plan === null ? null : (int) $plan->max_devices,
            'max_stores' => $plan === null ? null : (int) $plan->max_stores,
        ];
    }

    public function activeDeviceCount(Tenant $tenant): int
    {
        return $this->subscriptionStatus->activeDeviceCount($tenant);
    }
}
