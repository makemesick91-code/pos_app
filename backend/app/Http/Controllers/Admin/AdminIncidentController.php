<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SupportConsole\IncidentConsoleReadService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * UIX-6 — Platform Admin Incident Console (`/admin/incidents`).
 *
 * Read-only platform-global incident visibility over the canonical
 * {@see \App\Models\ProductionIncident} lifecycle, behind `platform.admin.web`.
 * Severity/status/impact are read verbatim (UIX6-R014); there is no mutation
 * route — UIX-6 ships read-only and defers incident transitions to a governed
 * mutation service (UIX6-R016/R017). Free text is redacted; evidence payloads
 * are never rendered (UIX6-R009/R019).
 */
class AdminIncidentController extends Controller
{
    public function __construct(
        private readonly IncidentConsoleReadService $incidents,
        private readonly AdminAuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'status' => $request->query('status'),
            'severity' => $request->query('severity'),
            'open_only' => $request->boolean('open_only'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
            'per_page' => (int) $request->query('per_page', 20),
        ];

        $paginator = $this->incidents->paginateProductionIncidents($filters);

        return view('admin.incidents.index', [
            'paginator' => $paginator,
            'rows' => array_map(
                fn ($incident) => $this->incidents->presentProductionIncident($incident),
                $paginator->items(),
            ),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, int $incident): View
    {
        $model = $this->incidents->findProductionIncident($incident);
        abort_if($model === null, 404);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_ADMIN_INCIDENT_VIEWED,
            targetType: AdminAuditLog::TARGET_PRODUCTION_INCIDENT,
            targetId: (int) $model->id,
            tenantId: $model->tenant_id !== null ? (int) $model->tenant_id : null,
            metadata: ['channel' => 'web_console', 'incident_reference' => $model->incident_reference],
            request: $request,
        );

        return view('admin.incidents.show', ['incident' => $this->incidents->presentProductionIncident($model)]);
    }
}
