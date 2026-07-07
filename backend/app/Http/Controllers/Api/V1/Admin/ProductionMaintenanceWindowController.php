<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IndexProductionMaintenanceWindowRequest;
use App\Http\Requests\Api\V1\Admin\StoreProductionMaintenanceWindowRequest;
use App\Http\Requests\Api\V1\Admin\TransitionProductionMaintenanceWindowStatusRequest;
use App\Http\Requests\Api\V1\Admin\UpdateProductionMaintenanceWindowRequest;
use App\Http\Resources\Api\V1\Admin\ProductionMaintenanceWindowResource;
use App\Models\AdminAuditLog;
use App\Models\ProductionMaintenanceWindow;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Operations\MaintenanceWindowService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 19 — platform-admin production maintenance windows. Platform admin only
 * (platform.admin middleware); tenant business users are blocked. A maintenance
 * window record never performs a deployment. Every action is recorded to the
 * admin audit log. No secrets are exposed.
 */
class ProductionMaintenanceWindowController extends Controller
{
    public function __construct(
        private readonly MaintenanceWindowService $windows,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(IndexProductionMaintenanceWindowRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = ProductionMaintenanceWindow::query()->latest('id');
        foreach (['status', 'risk_level'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return ProductionMaintenanceWindowResource::collection(
            $query->paginate((int) ($filters['per_page'] ?? 20)),
        );
    }

    public function store(StoreProductionMaintenanceWindowRequest $request): ProductionMaintenanceWindowResource
    {
        $window = $this->windows->create($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_MAINTENANCE_CREATED,
            targetType: AdminAuditLog::TARGET_PRODUCTION_MAINTENANCE_WINDOW,
            targetId: $window->id,
            after: ['status' => $window->status, 'risk_level' => $window->risk_level, 'has_rollback_plan' => $window->hasRollbackPlan()],
            request: $request,
        );

        return new ProductionMaintenanceWindowResource($window);
    }

    public function show(ProductionMaintenanceWindow $maintenanceWindow): ProductionMaintenanceWindowResource
    {
        return new ProductionMaintenanceWindowResource($maintenanceWindow);
    }

    public function update(UpdateProductionMaintenanceWindowRequest $request, ProductionMaintenanceWindow $maintenanceWindow): ProductionMaintenanceWindowResource
    {
        $maintenanceWindow = $this->windows->update($maintenanceWindow, $request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_MAINTENANCE_UPDATED,
            targetType: AdminAuditLog::TARGET_PRODUCTION_MAINTENANCE_WINDOW,
            targetId: $maintenanceWindow->id,
            after: ['status' => $maintenanceWindow->status, 'risk_level' => $maintenanceWindow->risk_level],
            request: $request,
        );

        return new ProductionMaintenanceWindowResource($maintenanceWindow);
    }

    public function status(TransitionProductionMaintenanceWindowStatusRequest $request, ProductionMaintenanceWindow $maintenanceWindow): ProductionMaintenanceWindowResource
    {
        $before = $maintenanceWindow->status;
        $maintenanceWindow = $this->windows->transitionStatus($maintenanceWindow, $request->validated()['status'], $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_MAINTENANCE_STATUS_CHANGED,
            targetType: AdminAuditLog::TARGET_PRODUCTION_MAINTENANCE_WINDOW,
            targetId: $maintenanceWindow->id,
            before: ['status' => $before],
            after: ['status' => $maintenanceWindow->status],
            request: $request,
        );

        return new ProductionMaintenanceWindowResource($maintenanceWindow);
    }
}
