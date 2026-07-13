<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SupportConsole\SupportConsoleReadService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * UIX-6 — Platform Admin Support Console (`/admin/support/*`).
 *
 * Read-only, behind the `platform.admin.web` route gate (enforced on the route
 * group, never inline). Platform admins read across tenants BY DESIGN, through
 * platform authorization only — never tenant-owner membership (UIX6-R006). There
 * are NO mutation routes; the only writes are audit records (UIX6-R015/R016).
 */
class AdminSupportController extends Controller
{
    public function __construct(
        private readonly SupportConsoleReadService $support,
        private readonly AdminAuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $this->auditLogger->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_ADMIN_SUPPORT_VIEWED,
            targetType: AdminAuditLog::TARGET_SUPPORT_CONSOLE,
            metadata: ['channel' => 'web_console', 'scope' => 'platform'],
            request: $request,
        );

        return view('admin.support.overview', ['data' => $this->support->overview()]);
    }

    public function tenants(Request $request): View
    {
        $filters = [
            'search' => $request->query('q'),
            'status' => $request->query('status'),
            'per_page' => (int) $request->query('per_page', 15),
        ];

        $paginator = $this->support->tenantList($filters);

        return view('admin.support.tenants', [
            'paginator' => $paginator,
            'rows' => array_map(
                fn (Tenant $tenant) => [
                    'tenant' => $tenant,
                    'brief' => $this->support->briefStatus($tenant),
                ],
                $paginator->items(),
            ),
            'filters' => $filters,
        ]);
    }

    public function tenantDetail(Request $request, Tenant $tenant): View
    {
        $this->auditLogger->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_ADMIN_SUPPORT_VIEWED,
            targetType: AdminAuditLog::TARGET_TENANT,
            tenantId: (int) $tenant->id,
            metadata: ['channel' => 'web_console', 'scope' => 'tenant'],
            request: $request,
        );

        return view('admin.support.tenant', ['data' => $this->support->tenantDetail($tenant)]);
    }
}
