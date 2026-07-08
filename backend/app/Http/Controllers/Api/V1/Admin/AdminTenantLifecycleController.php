<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\TenantLifecycleResource;
use App\Models\Tenant;
use App\Services\TenantLifecycle\TenantLifecycleService;

/**
 * Sprint 25 — read-only tenant lifecycle view for platform admins. Returns the
 * authoritative lifecycle decision (computed server-side), the active manual
 * suspension if any, and the recent lifecycle event trail. Platform admin only.
 */
class AdminTenantLifecycleController extends Controller
{
    public function __construct(
        private readonly TenantLifecycleService $lifecycle,
    ) {}

    public function show(Tenant $tenant): TenantLifecycleResource
    {
        $decision = $this->lifecycle->resolve($tenant);

        return new TenantLifecycleResource([
            'tenant' => $tenant,
            'decision' => $decision,
            'active_suspension' => $tenant->activeManualSuspension(),
            'events' => $tenant->lifecycleEvents()->orderByDesc('id')->limit(20)->get(),
        ]);
    }
}
