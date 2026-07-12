<?php

namespace App\Services\OwnerConsole;

use App\Models\Store;
use App\Models\TenantDeviceActivation;
use App\Services\Entitlements\EntitlementUsageService;
use App\Services\Reports\DailySalesReportService;
use App\Services\SupportOperations\SupportAndroidRuntimeViewerService;
use App\Services\SupportOperations\SupportBillingViewerService;
use App\Services\SupportOperations\SupportOnboardingViewerService;
use App\Services\SupportOperations\SupportTenantHealthService;
use App\Services\TenantPlan\TenantPlanResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * UIX-4 — assembles read-only, tenant-scoped view models for the Tenant Owner
 * Web Console from the EXISTING canonical domain services (UIX4-R009). It
 * orchestrates and shapes reads for presentation; it never computes billing,
 * entitlement, usage, lifecycle, or plan state itself and never mutates
 * anything (UIX4-R011).
 *
 * Every panel is wrapped so an unavailable/failed downstream read degrades to a
 * truthful `['available' => false]` state rather than a fabricated zero
 * (UIX4-R010). Genuine zeros (e.g. "no invoices yet") remain real zeros.
 */
class OwnerConsoleReadService
{
    /** Columns an owner may sort the outlet list by (whitelist — UIX4 §17). */
    private const OUTLET_SORTS = ['name', 'code', 'created_at', 'updated_at'];

    public function __construct(
        private readonly TenantPlanResolver $plans,
        private readonly EntitlementUsageService $usage,
        private readonly SupportBillingViewerService $billing,
        private readonly SupportOnboardingViewerService $onboarding,
        private readonly SupportAndroidRuntimeViewerService $androidRuntime,
        private readonly SupportTenantHealthService $health,
        private readonly DailySalesReportService $sales,
    ) {}

    /**
     * The consolidated owner dashboard view model.
     *
     * @return array<string, mixed>
     */
    public function dashboard(OwnerContext $context): array
    {
        $tenantId = $context->tenantId();

        return [
            'lifecycle' => $context->lifecycle,
            'operational' => $context->operational(),
            'outlets' => $this->safe(fn () => [
                'total' => Store::query()->where('tenant_id', $tenantId)->count(),
                'active' => Store::query()->where('tenant_id', $tenantId)->where('is_active', true)->count(),
            ]),
            'devices' => $this->safe(fn () => $this->deviceCounts($tenantId)),
            'plan' => $this->safe(fn () => $this->planSummary($context)),
            'usage' => $this->safe(fn () => $this->usage->summary($context->tenant)),
            'billing' => $this->safe(fn () => $this->billing->summary($tenantId)),
            'health' => $this->safe(fn () => $this->healthSummary($context)),
            'sales_today' => $this->safe(fn () => $this->sales->summary($tenantId)),
        ];
    }

    /**
     * Tenant-scoped, paginated outlet list with search / status filter / safe
     * sort. Every row is constrained to the owner's tenant (UIX4-R006).
     *
     * @return LengthAwarePaginator<Store>
     */
    public function outlets(OwnerContext $context, ?string $search, string $status, string $sort, string $direction, int $perPage): LengthAwarePaginator
    {
        $sort = in_array($sort, self::OUTLET_SORTS, true) ? $sort : 'name';
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $perPage = max(10, min($perPage, 50));

        $query = Store::query()->where('tenant_id', $context->tenantId());

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($search !== null && $search !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)->orWhere('code', 'like', $term);
            });
        }

        return $query->orderBy($sort, $direction)->paginate($perPage)->withQueryString();
    }

    /**
     * Resolve a single outlet within the owner's tenant, or null when it does
     * not belong to the tenant (UIX4-R006/R007). The caller renders 404.
     */
    public function findOutlet(OwnerContext $context, int $outletId): ?Store
    {
        return Store::query()
            ->where('tenant_id', $context->tenantId())
            ->find($outletId);
    }

    /**
     * @return array<string, mixed>
     */
    public function outletDetail(OwnerContext $context, Store $outlet): array
    {
        return [
            'outlet' => $outlet,
            'user_count' => $this->safe(fn () => $outlet->users()->count()),
            'active_user_count' => $this->safe(fn () => $outlet->users()->where('is_active', true)->count()),
            'devices' => $this->safe(fn () => $this->outletDevices($context->tenantId(), (int) $outlet->id)),
        ];
    }

    /**
     * Tenant-scoped device activation list (safe columns only — the token hash
     * and fingerprint hash are never exposed, UIX4-R016).
     *
     * @return LengthAwarePaginator<TenantDeviceActivation>
     */
    public function devices(OwnerContext $context, ?string $search, string $status, int $perPage): LengthAwarePaginator
    {
        $perPage = max(10, min($perPage, 50));

        $query = TenantDeviceActivation::query()->where('tenant_id', $context->tenantId());

        $allowedStatuses = [
            TenantDeviceActivation::STATUS_PENDING,
            TenantDeviceActivation::STATUS_ACTIVATED,
            TenantDeviceActivation::STATUS_REVOKED,
            TenantDeviceActivation::STATUS_EXPIRED,
            TenantDeviceActivation::STATUS_FAILED,
        ];
        if (in_array($status, $allowedStatuses, true)) {
            $query->where('activation_status', $status);
        }

        if ($search !== null && $search !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where('device_label', 'like', $term);
        }

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    public function findDevice(OwnerContext $context, int $deviceId): ?TenantDeviceActivation
    {
        return TenantDeviceActivation::query()
            ->where('tenant_id', $context->tenantId())
            ->find($deviceId);
    }

    /**
     * @return array<string, mixed>
     */
    public function subscription(OwnerContext $context): array
    {
        return [
            'lifecycle' => $context->lifecycle,
            'plan' => $this->safe(fn () => $this->planSummary($context)),
            'billing' => $this->safe(fn () => $this->billing->summary($context->tenantId())),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function usage(OwnerContext $context): array
    {
        return [
            'plan' => $this->safe(fn () => $this->planSummary($context)),
            'usage' => $this->safe(fn () => $this->usage->summary($context->tenant)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function operations(OwnerContext $context): array
    {
        $tenantId = $context->tenantId();

        return [
            'health' => $this->safe(fn () => $this->healthSummary($context)),
            'onboarding' => $this->safe(fn () => $this->onboarding->summary($tenantId)),
            'sync' => $this->safe(fn () => $this->androidRuntime->summary($tenantId)),
            'sync_failures' => $this->safe(fn () => $this->androidRuntime->syncFailures($tenantId)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function planSummary(OwnerContext $context): array
    {
        $decision = $this->plans->resolve($context->tenant);

        return [
            'plan_key' => $decision->planKey,
            'plan_name' => $decision->planName,
            'has_explicit_assignment' => $decision->hasExplicitAssignment,
            'entitlements' => $decision->entitlements,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function healthSummary(OwnerContext $context): array
    {
        $overview = $this->health->overview($context->tenant);

        return [
            'health_status' => $overview['health_status'] ?? null,
            'reason_codes' => $overview['reason_codes'] ?? [],
            'manual_suspension_active' => $overview['manual_suspension_active'] ?? false,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function deviceCounts(int $tenantId): array
    {
        $summary = $this->androidRuntime->summary($tenantId);

        return [
            'total' => (int) ($summary['device_count'] ?? 0),
            'revoked' => (int) ($summary['revoked_device_count'] ?? 0),
            'activated' => (int) ($summary['devices_by_status'][TenantDeviceActivation::STATUS_ACTIVATED] ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function outletDevices(int $tenantId, int $outletId): array
    {
        return TenantDeviceActivation::query()
            ->where('tenant_id', $tenantId)
            ->where('store_id', $outletId)
            ->orderByDesc('id')
            ->limit(25)
            ->get()
            ->map(fn (TenantDeviceActivation $d) => $d->toSafeArray())
            ->all();
    }

    /**
     * Run a read and normalise it into an availability-tagged panel. A failed
     * downstream read never leaks as a fabricated zero.
     *
     * @param  callable(): mixed  $read
     * @return array<string, mixed>
     */
    private function safe(callable $read): array
    {
        try {
            $value = $read();
        } catch (Throwable $e) {
            Log::warning('owner.console.panel_unavailable', [
                'exception' => $e::class,
            ]);

            return ['available' => false];
        }

        if (is_array($value)) {
            return ['available' => true] + $value;
        }

        return ['available' => true, 'value' => $value];
    }
}
