<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IndexAdminTenantRequest;
use App\Http\Resources\Api\V1\Admin\AdminTenantDetailResource;
use App\Http\Resources\Api\V1\Admin\AdminTenantResource;
use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Admin\AdminTenantService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 11 — admin tenant list/detail. Platform admin only (platform.admin
 * middleware). Cross-tenant reads flow only through AdminTenantService; secrets
 * and raw gateway payloads are never exposed.
 */
class AdminTenantController extends Controller
{
    public function __construct(
        private readonly AdminTenantService $tenants,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(IndexAdminTenantRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->tenants->paginate($request->validated());

        $paginator->getCollection()->each(function (Tenant $tenant): void {
            $tenant->setAttribute('subscription_summary', $this->tenants->subscriptionSummary($tenant));
        });

        return AdminTenantResource::collection($paginator)->additional([
            'meta' => [
                'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
            ],
        ]);
    }

    public function show(Tenant $tenant): JsonResource
    {
        $tenant->loadMissing('stores');
        $tenant->setAttribute('subscription_summary', $this->tenants->subscriptionSummary($tenant));
        $tenant->setAttribute('devices_active_count', $this->tenants->activeDeviceCount($tenant));

        $this->audit->log(
            actor: request()->user(),
            action: AdminAuditLog::ACTION_TENANT_VIEWED,
            targetType: AdminAuditLog::TARGET_TENANT,
            targetId: $tenant->id,
            tenantId: $tenant->id,
            request: request(),
        );

        return AdminTenantDetailResource::make($tenant)->additional([
            'meta' => [
                'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
            ],
        ]);
    }
}
