<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IndexProductionHandoverRequest;
use App\Http\Requests\Api\V1\Admin\StoreProductionHandoverRequest;
use App\Http\Requests\Api\V1\Admin\UpdateProductionHandoverRequest;
use App\Http\Resources\Api\V1\Admin\ProductionHandoverPackageResource;
use App\Models\AdminAuditLog;
use App\Models\ProductionHandoverPackage;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Handover\ProductionHandoverService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 18 — platform-admin production handover packages. Platform admin only
 * (platform.admin middleware); tenant business users are blocked. Status
 * transitions are conservative (mark-ready / mark-handed-over only). Every action
 * is recorded to the admin audit log. No secrets or credentials are exposed.
 */
class ProductionHandoverController extends Controller
{
    public function __construct(
        private readonly ProductionHandoverService $handover,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(IndexProductionHandoverRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = ProductionHandoverPackage::query()->latest('id');
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return ProductionHandoverPackageResource::collection(
            $query->paginate((int) ($filters['per_page'] ?? 20)),
        );
    }

    public function store(StoreProductionHandoverRequest $request): ProductionHandoverPackageResource
    {
        $package = $this->handover->create($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_HANDOVER_CREATED,
            targetType: AdminAuditLog::TARGET_PRODUCTION_HANDOVER_PACKAGE,
            targetId: $package->id,
            after: ['status' => $package->status, 'decision' => $package->decision],
            request: $request,
        );

        return new ProductionHandoverPackageResource($package->load('signoffs'));
    }

    public function show(ProductionHandoverPackage $handover): ProductionHandoverPackageResource
    {
        return new ProductionHandoverPackageResource($handover->load('signoffs'));
    }

    public function update(UpdateProductionHandoverRequest $request, ProductionHandoverPackage $handover): ProductionHandoverPackageResource
    {
        $handover = $this->handover->update($handover, $request->validated());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_HANDOVER_UPDATED,
            targetType: AdminAuditLog::TARGET_PRODUCTION_HANDOVER_PACKAGE,
            targetId: $handover->id,
            after: ['status' => $handover->status],
            request: $request,
        );

        return new ProductionHandoverPackageResource($handover->load('signoffs'));
    }

    public function markReady(ProductionHandoverPackage $handover): ProductionHandoverPackageResource
    {
        $before = $handover->status;
        $handover = $this->handover->markReady($handover);

        $this->audit->log(
            actor: request()->user(),
            action: AdminAuditLog::ACTION_HANDOVER_MARKED_READY,
            targetType: AdminAuditLog::TARGET_PRODUCTION_HANDOVER_PACKAGE,
            targetId: $handover->id,
            before: ['status' => $before],
            after: ['status' => $handover->status, 'decision' => $handover->decision],
            request: request(),
        );

        return new ProductionHandoverPackageResource($handover->load('signoffs'));
    }

    public function markHandedOver(ProductionHandoverPackage $handover): ProductionHandoverPackageResource
    {
        $before = $handover->status;
        $handover = $this->handover->markHandedOver($handover, request()->user());

        $this->audit->log(
            actor: request()->user(),
            action: AdminAuditLog::ACTION_HANDOVER_HANDED_OVER,
            targetType: AdminAuditLog::TARGET_PRODUCTION_HANDOVER_PACKAGE,
            targetId: $handover->id,
            before: ['status' => $before],
            after: ['status' => $handover->status],
            request: request(),
        );

        return new ProductionHandoverPackageResource($handover->load('signoffs'));
    }
}
