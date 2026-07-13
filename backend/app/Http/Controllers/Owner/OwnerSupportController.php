<?php

namespace App\Http\Controllers\Owner;

use App\Services\OwnerConsole\OwnerConsoleReadService;
use App\Services\OwnerConsole\OwnerContextResolver;
use App\Services\SupportConsole\IncidentConsoleReadService;
use App\Services\SupportConsole\OwnerSupportReadService;
use Illuminate\Contracts\View\View;

/**
 * UIX-6 — Tenant Owner Support / Operational view (`/owner/support`).
 *
 * Runs on the dedicated `owner` guard behind `tenant.owner.web`. STRICTLY
 * tenant-scoped to the authenticated owner's own tenant (server-resolved via
 * {@see OwnerContext}); the owner never sees another tenant, platform-global
 * observability, the affected-tenant list, raw logs, or infrastructure detail
 * (UIX6-R004/R005/R010).
 *
 * Deliberately NOT gated by {@see OwnerController::whenOperational()} — support
 * and health status is exactly what a suspended/degraded owner most needs to
 * see, and it exposes no business listings.
 */
class OwnerSupportController extends OwnerController
{
    public function __construct(
        OwnerContextResolver $resolver,
        OwnerConsoleReadService $read,
        private readonly OwnerSupportReadService $support,
        private readonly IncidentConsoleReadService $incidents,
    ) {
        parent::__construct($resolver, $read);
    }

    public function index(): View
    {
        $context = $this->context();

        return view('owner.support.overview', [
            'context' => $context,
            'data' => $this->support->overview($context),
        ]);
    }

    public function showIncident(int $incident): View
    {
        $context = $this->context();

        $model = $this->incidents->findTenantIncident($context->tenantId(), $incident);
        abort_if($model === null, 404);

        return view('owner.support.incident', [
            'context' => $context,
            'incident' => $this->incidents->presentTenantIncident($model),
        ]);
    }
}
