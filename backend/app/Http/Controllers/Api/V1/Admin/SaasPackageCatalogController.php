<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ApproveSaasPackageCatalogRequest;
use App\Http\Requests\Api\V1\Admin\IndexSaasPackageCatalogRequest;
use App\Http\Requests\Api\V1\Admin\RetireSaasPackageCatalogRequest;
use App\Http\Requests\Api\V1\Admin\StoreSaasPackageCatalogRequest;
use App\Http\Requests\Api\V1\Admin\UpdateSaasPackageCatalogRequest;
use App\Http\Resources\Api\V1\Admin\SaasPackageCatalogResource;
use App\Models\AdminAuditLog;
use App\Models\SaasPackageCatalog;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Commercial\SaaSPackageCatalogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 20 — platform-admin SaaS package catalog. Platform admin only. Package
 * pricing is governance metadata only; approving/activating a package starts no
 * real billing and never bypasses subscription/device runtime enforcement. Every
 * mutation is audit-logged. No secrets are exposed.
 */
class SaasPackageCatalogController extends Controller
{
    public function __construct(
        private readonly SaaSPackageCatalogService $packages,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(IndexSaasPackageCatalogRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = SaasPackageCatalog::query()->latest('id');
        foreach (['status', 'target_segment'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return SaasPackageCatalogResource::collection(
            $query->paginate((int) ($filters['per_page'] ?? 20)),
        );
    }

    public function store(StoreSaasPackageCatalogRequest $request): SaasPackageCatalogResource
    {
        $package = $this->packages->create($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_PACKAGE_CREATED,
            targetType: AdminAuditLog::TARGET_SAAS_PACKAGE_CATALOG,
            targetId: $package->id,
            after: ['status' => $package->status, 'package_code' => $package->package_code],
            request: $request,
        );

        return new SaasPackageCatalogResource($package);
    }

    public function show(SaasPackageCatalog $package): SaasPackageCatalogResource
    {
        return new SaasPackageCatalogResource($package);
    }

    public function update(UpdateSaasPackageCatalogRequest $request, SaasPackageCatalog $package): SaasPackageCatalogResource
    {
        $before = ['status' => $package->status];
        $package = $this->packages->update($package, $request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_PACKAGE_UPDATED,
            targetType: AdminAuditLog::TARGET_SAAS_PACKAGE_CATALOG,
            targetId: $package->id,
            before: $before,
            after: ['status' => $package->status],
            request: $request,
        );

        return new SaasPackageCatalogResource($package);
    }

    public function approve(ApproveSaasPackageCatalogRequest $request, SaasPackageCatalog $package): SaasPackageCatalogResource
    {
        $before = ['status' => $package->status];
        $package = $this->packages->approve($package, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_PACKAGE_APPROVED,
            targetType: AdminAuditLog::TARGET_SAAS_PACKAGE_CATALOG,
            targetId: $package->id,
            before: $before,
            after: ['status' => $package->status],
            request: $request,
        );

        return new SaasPackageCatalogResource($package);
    }

    public function retire(RetireSaasPackageCatalogRequest $request, SaasPackageCatalog $package): SaasPackageCatalogResource
    {
        $before = ['status' => $package->status];
        $package = $this->packages->retire($package, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_PACKAGE_RETIRED,
            targetType: AdminAuditLog::TARGET_SAAS_PACKAGE_CATALOG,
            targetId: $package->id,
            before: $before,
            after: ['status' => $package->status],
            request: $request,
        );

        return new SaasPackageCatalogResource($package);
    }
}
