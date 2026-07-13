<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SupportConsole\ObservabilityConsoleReadService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * UIX-6 — Platform Admin Observability Console (`/admin/observability`).
 *
 * Read-only browser presentation over the Sprint 36 canonical observability
 * services, behind the `platform.admin.web` route gate. Distinct from the
 * existing `App\Http\Controllers\Api\V1\Admin\AdminObservabilityController`
 * (JSON API); the two agree because they read the same services (UIX6-R001).
 *
 * The view is truthful about freshness: a component whose evidence is missing or
 * stale is presented as UNKNOWN, never healthy (UIX6-R011/R012/R013).
 */
class AdminObservabilityWebController extends Controller
{
    public function __construct(
        private readonly ObservabilityConsoleReadService $observability,
        private readonly AdminAuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $this->auditLogger->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_ADMIN_OBSERVABILITY_VIEWED,
            targetType: AdminAuditLog::TARGET_OBSERVABILITY_CONSOLE,
            metadata: ['channel' => 'web_console', 'scope' => 'platform'],
            request: $request,
        );

        return view('admin.observability.overview', ['data' => $this->observability->overview()]);
    }
}
