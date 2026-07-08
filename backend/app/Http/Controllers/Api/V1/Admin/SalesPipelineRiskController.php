<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AcceptSalesPipelineRiskRequest;
use App\Http\Requests\Api\V1\Admin\CloseSalesPipelineRiskRequest;
use App\Http\Requests\Api\V1\Admin\StoreSalesPipelineRiskRequest;
use App\Http\Requests\Api\V1\Admin\UpdateSalesPipelineRiskRequest;
use App\Http\Resources\Api\V1\Admin\SalesPipelineRiskResource;
use App\Models\AdminAuditLog;
use App\Models\SalesPipelineRisk;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SalesPipeline\SalesPipelineRiskGovernanceService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 22 — platform-admin sales pipeline risks. Platform admin only. Open
 * CRITICAL/HIGH without a valid accepted risk forces NO-GO; open MEDIUM forces
 * WATCH. Every mutation is audit-logged. No secrets are exposed.
 */
class SalesPipelineRiskController extends Controller
{
    public function __construct(
        private readonly SalesPipelineRiskGovernanceService $risks,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(\Illuminate\Http\Request $request): AnonymousResourceCollection
    {
        $query = SalesPipelineRisk::query()->latest('id');
        foreach (['severity', 'status', 'area'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return SalesPipelineRiskResource::collection(
            $query->paginate((int) $request->integer('per_page', 20)),
        );
    }

    public function store(StoreSalesPipelineRiskRequest $request): SalesPipelineRiskResource
    {
        $risk = $this->risks->create($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_RISK_CREATED,
            targetType: AdminAuditLog::TARGET_SALES_PIPELINE_RISK,
            targetId: $risk->id,
            after: ['severity' => $risk->severity, 'status' => $risk->status],
            request: $request,
        );

        return new SalesPipelineRiskResource($risk);
    }

    public function show(SalesPipelineRisk $risk): SalesPipelineRiskResource
    {
        return new SalesPipelineRiskResource($risk);
    }

    public function update(UpdateSalesPipelineRiskRequest $request, SalesPipelineRisk $risk): SalesPipelineRiskResource
    {
        $before = ['severity' => $risk->severity, 'status' => $risk->status];
        $risk = $this->risks->update($risk, $request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_RISK_UPDATED,
            targetType: AdminAuditLog::TARGET_SALES_PIPELINE_RISK,
            targetId: $risk->id,
            before: $before,
            after: ['severity' => $risk->severity, 'status' => $risk->status],
            request: $request,
        );

        return new SalesPipelineRiskResource($risk);
    }

    public function acceptRisk(AcceptSalesPipelineRiskRequest $request, SalesPipelineRisk $risk): SalesPipelineRiskResource
    {
        $data = $request->validated();
        if (isset($data['approver_id'])) {
            $data['approver'] = $data['approver_id'];
        }
        $before = ['status' => $risk->status];
        $risk = $this->risks->acceptRisk($risk, $data, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_RISK_ACCEPTED,
            targetType: AdminAuditLog::TARGET_SALES_PIPELINE_RISK,
            targetId: $risk->id,
            before: $before,
            after: ['status' => $risk->status],
            request: $request,
        );

        return new SalesPipelineRiskResource($risk);
    }

    public function close(CloseSalesPipelineRiskRequest $request, SalesPipelineRisk $risk): SalesPipelineRiskResource
    {
        $before = ['status' => $risk->status];
        $risk = $this->risks->close($risk, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_RISK_CLOSED,
            targetType: AdminAuditLog::TARGET_SALES_PIPELINE_RISK,
            targetId: $risk->id,
            before: $before,
            after: ['status' => $risk->status],
            request: $request,
        );

        return new SalesPipelineRiskResource($risk);
    }
}
