<?php

namespace App\Services\SupportConsole;

use App\Models\ProductionIncident;
use App\Models\Tenant;
use App\Services\Observability\ObservabilityMetricsService;
use App\Services\Observability\TenantRuntimeProbeService;
use App\Services\SupportOperations\SupportAndroidRuntimeViewerService;
use App\Services\SupportOperations\SupportBillingViewerService;
use App\Services\SupportOperations\SupportDiagnosticTimelineService;
use App\Services\SupportOperations\SupportOnboardingViewerService;
use App\Services\SupportOperations\SupportTenantHealthService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * UIX-6 — read adapter for the Platform Admin Support Console
 * (`/admin/support/*`). It orchestrates the EXISTING Sprint 35 support services
 * plus Sprint 36 metrics and shapes them for Blade (UIX6-R001/R002). Tenant
 * health, billing, sync, onboarding, and incident state are read verbatim from
 * canonical services — never recomputed.
 *
 * Platform admins read across tenants BY DESIGN, through platform authorization;
 * this is never tenant-owner membership (UIX6-R006). Every downstream failure
 * degrades to a truthful `['available' => false]` panel (UIX6-R013).
 */
class SupportConsoleReadService
{
    public function __construct(
        private readonly SupportTenantHealthService $health,
        private readonly SupportDiagnosticTimelineService $timeline,
        private readonly SupportBillingViewerService $billing,
        private readonly SupportOnboardingViewerService $onboarding,
        private readonly SupportAndroidRuntimeViewerService $androidRuntime,
        private readonly TenantRuntimeProbeService $tenantProbe,
        private readonly ObservabilityMetricsService $metrics,
        private readonly IncidentConsoleReadService $incidents,
    ) {}

    /**
     * The consolidated admin support overview view model.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return [
            'metrics' => $this->safe(fn () => $this->metrics->summary()),
            'degraded_tenants' => $this->safe(fn () => ['count' => $this->tenantProbe->degradedCount()]),
            'recent_incidents' => $this->safe(fn () => ['items' => $this->recentOpenIncidents()]),
        ];
    }

    /**
     * Paginated, filtered tenant health list. Health for each row on the page is
     * computed by the canonical service; the query is bounded per page so the
     * request never runs an unbounded scan (UIX6-R022).
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Tenant>
     */
    public function tenantList(array $filters): LengthAwarePaginator
    {
        $perPage = max(10, min((int) ($filters['per_page'] ?? 15), 25));

        $query = Tenant::query();

        $status = $filters['status'] ?? null;
        if (in_array($status, [Tenant::STATUS_ACTIVE, Tenant::STATUS_SUSPENDED, Tenant::STATUS_INACTIVE], true)) {
            $query->where('status', $status);
        }

        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)->orWhere('code', 'like', $term);
            });
        }

        return $query->orderBy('name')->paginate($perPage)->withQueryString();
    }

    /**
     * Brief health for one tenant row (used to decorate the list page). Failure
     * degrades to an unavailable marker, never a fabricated healthy status.
     *
     * @return array<string, mixed>
     */
    public function briefStatus(Tenant $tenant): array
    {
        return $this->safe(fn () => $this->health->briefStatus($tenant));
    }

    /**
     * The tenant support detail view model — authoritative health, redacted
     * diagnostic timeline, canonical viewers, and the tenant's own incidents.
     *
     * @return array<string, mixed>
     */
    public function tenantDetail(Tenant $tenant): array
    {
        $tenantId = (int) $tenant->id;

        return [
            'tenant' => $tenant,
            'health' => $this->safe(fn () => $this->health->overview($tenant)),
            'timeline' => $this->safe(fn () => $this->timeline->build($tenant)),
            'billing' => $this->safe(fn () => $this->billing->summary($tenantId)),
            'onboarding' => $this->safe(fn () => $this->onboarding->summary($tenantId)),
            'sync' => $this->safe(fn () => $this->androidRuntime->summary($tenantId)),
            'sync_failures' => $this->safe(fn () => $this->androidRuntime->syncFailures($tenantId)),
            'incidents' => $this->safe(fn () => ['items' => $this->incidents->tenantIncidents($tenantId)]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentOpenIncidents(): array
    {
        return ProductionIncident::query()
            ->whereIn('status', ProductionIncident::OPEN_STATUSES)
            ->orderByDesc('detected_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (ProductionIncident $i) => [
                'id' => (int) $i->id,
                'reference' => $i->incident_reference,
                'severity' => $i->severity,
                'status' => $i->status,
                'area' => $i->area,
                'detected_at' => optional($i->detected_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @param  callable(): mixed  $read
     * @return array<string, mixed>
     */
    private function safe(callable $read): array
    {
        try {
            $value = $read();
        } catch (Throwable $e) {
            Log::warning('admin.support.panel_unavailable', ['exception' => $e::class]);

            return ['available' => false];
        }

        if (is_array($value)) {
            return ['available' => true] + $value;
        }

        return ['available' => true, 'value' => $value];
    }
}
