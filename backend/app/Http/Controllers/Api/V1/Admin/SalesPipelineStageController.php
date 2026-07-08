<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreSalesPipelineStageRequest;
use App\Http\Requests\Api\V1\Admin\UpdateSalesPipelineStageRequest;
use App\Http\Resources\Api\V1\Admin\SalesPipelineStageResource;
use App\Models\AdminAuditLog;
use App\Models\SalesPipelineStage;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SalesPipeline\SalesPipelineStageService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 22 — platform-admin sales pipeline stages. Platform admin only. Stages
 * are governance metadata; a stage never creates a tenant/user/subscription/
 * device. Every mutation is audit-logged. No secrets are exposed.
 */
class SalesPipelineStageController extends Controller
{
    public function __construct(
        private readonly SalesPipelineStageService $stages,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return SalesPipelineStageResource::collection(
            SalesPipelineStage::query()->orderBy('sort_order')->orderBy('id')->get(),
        );
    }

    public function store(StoreSalesPipelineStageRequest $request): SalesPipelineStageResource
    {
        $stage = $this->stages->create($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_STAGE_CREATED,
            targetType: AdminAuditLog::TARGET_SALES_PIPELINE_STAGE,
            targetId: $stage->id,
            after: ['stage_code' => $stage->stage_code, 'status' => $stage->status],
            request: $request,
        );

        return new SalesPipelineStageResource($stage);
    }

    public function update(UpdateSalesPipelineStageRequest $request, SalesPipelineStage $stage): SalesPipelineStageResource
    {
        $before = ['stage_code' => $stage->stage_code, 'status' => $stage->status];
        $stage = $this->stages->update($stage, $request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_STAGE_UPDATED,
            targetType: AdminAuditLog::TARGET_SALES_PIPELINE_STAGE,
            targetId: $stage->id,
            before: $before,
            after: ['stage_code' => $stage->stage_code, 'status' => $stage->status],
            request: $request,
        );

        return new SalesPipelineStageResource($stage);
    }

    public function ensureDefaults(\Illuminate\Http\Request $request): AnonymousResourceCollection
    {
        $stages = $this->stages->ensureDefaults();

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_STAGES_ENSURED,
            targetType: AdminAuditLog::TARGET_SALES_PIPELINE_STAGE,
            after: ['count' => count($stages)],
            request: $request,
        );

        return SalesPipelineStageResource::collection(
            SalesPipelineStage::query()->orderBy('sort_order')->orderBy('id')->get(),
        );
    }
}
