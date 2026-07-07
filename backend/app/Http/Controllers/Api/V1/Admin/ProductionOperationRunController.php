<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ApproveProductionOperationRunRequest;
use App\Http\Requests\Api\V1\Admin\BlockProductionOperationRunRequest;
use App\Http\Requests\Api\V1\Admin\IndexProductionOperationRunRequest;
use App\Http\Requests\Api\V1\Admin\StoreProductionOperationRunRequest;
use App\Http\Resources\Api\V1\Admin\ProductionOperationRunResource;
use App\Models\AdminAuditLog;
use App\Models\ProductionOperationRun;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Operations\ProductionOperationsHealthService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 19 — platform-admin production operation runs. Platform admin only
 * (platform.admin middleware); tenant business users are blocked. A run records
 * the evidence-backed operations health/governance evaluation. Every action is
 * recorded to the admin audit log. No secrets are exposed; recording a run never
 * deploys, never runs real backup/restore, and never sends real alerts.
 */
class ProductionOperationRunController extends Controller
{
    public function __construct(
        private readonly ProductionOperationsHealthService $health,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(IndexProductionOperationRunRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = ProductionOperationRun::query()->latest('id');
        foreach (['status', 'decision'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return ProductionOperationRunResource::collection(
            $query->paginate((int) ($filters['per_page'] ?? 20)),
        );
    }

    public function store(StoreProductionOperationRunRequest $request): ProductionOperationRunResource
    {
        $run = $this->health->createRun($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_OPERATION_RUN_CREATED,
            targetType: AdminAuditLog::TARGET_PRODUCTION_OPERATION_RUN,
            targetId: $run->id,
            after: ['status' => $run->status, 'decision' => $run->decision],
            request: $request,
        );

        return new ProductionOperationRunResource($run);
    }

    public function show(ProductionOperationRun $operationRun): ProductionOperationRunResource
    {
        return new ProductionOperationRunResource($operationRun);
    }

    public function approve(ApproveProductionOperationRunRequest $request, ProductionOperationRun $operationRun): ProductionOperationRunResource
    {
        $before = $operationRun->status;
        $operationRun = $this->health->approve($operationRun, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_OPERATION_RUN_APPROVED,
            targetType: AdminAuditLog::TARGET_PRODUCTION_OPERATION_RUN,
            targetId: $operationRun->id,
            before: ['status' => $before],
            after: ['status' => $operationRun->status],
            request: $request,
        );

        return new ProductionOperationRunResource($operationRun);
    }

    public function block(BlockProductionOperationRunRequest $request, ProductionOperationRun $operationRun): ProductionOperationRunResource
    {
        $before = $operationRun->status;
        $operationRun = $this->health->block($operationRun, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_OPERATION_RUN_BLOCKED,
            targetType: AdminAuditLog::TARGET_PRODUCTION_OPERATION_RUN,
            targetId: $operationRun->id,
            before: ['status' => $before],
            after: ['status' => $operationRun->status],
            request: $request,
        );

        return new ProductionOperationRunResource($operationRun);
    }
}
