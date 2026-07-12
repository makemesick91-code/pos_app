<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Admin\AdminTenantService;
use App\Services\SupportOperations\SupportTenantHealthService;
use App\Services\TenantLifecycle\TenantLifecycleService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * UIX-3 — read-only browser tenant management for platform admins
 * (GET /admin/tenants, GET /admin/tenants/{tenant}).
 *
 * Reuses the Sprint 11 {@see AdminTenantService} for the paginated cross-tenant
 * query, the authoritative {@see TenantLifecycleService} for status (never
 * recomputed here), and the Sprint 35 {@see SupportTenantHealthService} for the
 * fused per-tenant detail (which applies its domain redactors). No tenant
 * request context is trusted; the platform admin reads across tenants by design,
 * and the only writes are audit records — there are NO mutation routes in this
 * foundation sprint.
 */
class AdminTenantWebController extends Controller
{
    /** Whitelisted sort columns to prevent SQL injection via sort param. */
    private const SORTABLE = ['id', 'name', 'code', 'created_at', 'updated_at'];

    /** Whitelisted lifecycle/status filter values (coarse tenant status). */
    private const STATUS_FILTERS = [
        Tenant::STATUS_ACTIVE,
        Tenant::STATUS_SUSPENDED,
        Tenant::STATUS_INACTIVE,
    ];

    public function __construct(
        private readonly AdminTenantService $tenants,
        private readonly TenantLifecycleService $lifecycle,
        private readonly SupportTenantHealthService $health,
        private readonly AdminAuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $status = in_array($status, self::STATUS_FILTERS, true) ? $status : '';

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(10, min($perPage, 50));

        $paginator = $this->tenants->paginate([
            'q' => $search !== '' ? $search : null,
            'status' => $status !== '' ? $status : null,
            'limit' => $perPage,
        ]);

        // Authoritative status per row — bounded to the page size, no full-table
        // fan-out. Never recompute lifecycle inline; always ask the service.
        $rows = $paginator->getCollection()->map(function (Tenant $tenant): array {
            $decision = $this->lifecycle->resolve($tenant);

            return [
                'id' => $tenant->id,
                'code' => $tenant->code,
                'name' => $tenant->name,
                'owner_name' => $tenant->owner_name,
                'status' => $tenant->status,
                'lifecycle_status' => $decision->status,
                'lifecycle_allowed' => $decision->allowed,
                'manually_suspended' => $decision->manuallySuspended,
                'stores_count' => (int) ($tenant->stores_count ?? 0),
                'devices_active_count' => (int) ($tenant->devices_active_count ?? 0),
                'subscription' => $this->safe(fn () => $this->tenants->subscriptionSummary($tenant)),
                'updated_at' => $tenant->updated_at,
            ];
        })->all();

        return view('admin.tenants.index', [
            'rows' => $rows,
            'paginator' => $paginator,
            'filters' => ['q' => $search, 'status' => $status, 'per_page' => $perPage],
            'statusOptions' => self::STATUS_FILTERS,
        ]);
    }

    public function show(Request $request, Tenant $tenant): View
    {
        $tenant->loadCount([
            'stores',
            'registeredDevices as devices_active_count' => fn (Builder $q) => $q->where('status', 'ACTIVE'),
        ]);

        $decision = $this->lifecycle->resolve($tenant);
        $overview = $this->safe(fn () => $this->health->overview($tenant));
        $subscription = $this->safe(fn () => $this->tenants->subscriptionSummary($tenant));

        // Attribute the cross-tenant read for governance. Metadata is sanitized
        // by the logger; no secret or PII beyond tenant identifiers is stored.
        $this->auditLogger->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_TENANT_VIEWED,
            targetType: 'Tenant',
            targetId: $tenant->id,
            tenantId: $tenant->id,
            metadata: ['channel' => 'web_console'],
            request: $request,
        );

        return view('admin.tenants.show', [
            'tenant' => $tenant,
            'lifecycle' => $decision->toArray(),
            'subscription' => $subscription,
            'overview' => $overview,
        ]);
    }

    /**
     * Run a supplemental read, degrading to a truthful unavailable marker rather
     * than failing the whole page if a downstream summary errors.
     *
     * @param  callable():array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    private function safe(callable $resolver): array
    {
        try {
            return ['available' => true] + $resolver();
        } catch (\Throwable) {
            return ['available' => false];
        }
    }
}
