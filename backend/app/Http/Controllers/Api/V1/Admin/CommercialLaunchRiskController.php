<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AcceptCommercialLaunchRiskRequest;
use App\Http\Requests\Api\V1\Admin\CloseCommercialLaunchRiskRequest;
use App\Http\Requests\Api\V1\Admin\IndexCommercialLaunchRiskRequest;
use App\Http\Requests\Api\V1\Admin\StoreCommercialLaunchRiskRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCommercialLaunchRiskRequest;
use App\Http\Resources\Api\V1\Admin\CommercialLaunchRiskResource;
use App\Models\AdminAuditLog;
use App\Models\CommercialLaunchRisk;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Commercial\CommercialRiskGovernanceService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 20 — platform-admin commercial launch risks. Platform admin only. Open
 * CRITICAL/HIGH without a valid accepted risk forces NO-GO; open MEDIUM forces
 * WATCH. Every mutation is audit-logged. No secrets or private customer data are
 * exposed.
 */
class CommercialLaunchRiskController extends Controller
{
    public function __construct(
        private readonly CommercialRiskGovernanceService $risks,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(IndexCommercialLaunchRiskRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = CommercialLaunchRisk::query()->latest('id');
        foreach (['severity', 'status', 'area'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return CommercialLaunchRiskResource::collection(
            $query->paginate((int) ($filters['per_page'] ?? 20)),
        );
    }

    public function store(StoreCommercialLaunchRiskRequest $request): CommercialLaunchRiskResource
    {
        $risk = $this->risks->create($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_COMMERCIAL_RISK_CREATED,
            targetType: AdminAuditLog::TARGET_COMMERCIAL_LAUNCH_RISK,
            targetId: $risk->id,
            after: ['severity' => $risk->severity, 'status' => $risk->status],
            request: $request,
        );

        return new CommercialLaunchRiskResource($risk);
    }

    public function show(CommercialLaunchRisk $risk): CommercialLaunchRiskResource
    {
        return new CommercialLaunchRiskResource($risk);
    }

    public function update(UpdateCommercialLaunchRiskRequest $request, CommercialLaunchRisk $risk): CommercialLaunchRiskResource
    {
        $before = ['severity' => $risk->severity, 'status' => $risk->status];
        $risk = $this->risks->update($risk, $request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_COMMERCIAL_RISK_UPDATED,
            targetType: AdminAuditLog::TARGET_COMMERCIAL_LAUNCH_RISK,
            targetId: $risk->id,
            before: $before,
            after: ['severity' => $risk->severity, 'status' => $risk->status],
            request: $request,
        );

        return new CommercialLaunchRiskResource($risk);
    }

    public function acceptRisk(AcceptCommercialLaunchRiskRequest $request, CommercialLaunchRisk $risk): CommercialLaunchRiskResource
    {
        $data = $request->validated();
        if (isset($data['approver_id'])) {
            $data['approver'] = $data['approver_id'];
        }
        $before = ['status' => $risk->status];
        $risk = $this->risks->acceptRisk($risk, $data, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_COMMERCIAL_RISK_ACCEPTED,
            targetType: AdminAuditLog::TARGET_COMMERCIAL_LAUNCH_RISK,
            targetId: $risk->id,
            before: $before,
            after: ['status' => $risk->status],
            request: $request,
        );

        return new CommercialLaunchRiskResource($risk);
    }

    public function close(CloseCommercialLaunchRiskRequest $request, CommercialLaunchRisk $risk): CommercialLaunchRiskResource
    {
        $before = ['status' => $risk->status];
        $risk = $this->risks->close($risk, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_COMMERCIAL_RISK_CLOSED,
            targetType: AdminAuditLog::TARGET_COMMERCIAL_LAUNCH_RISK,
            targetId: $risk->id,
            before: $before,
            after: ['status' => $risk->status],
            request: $request,
        );

        return new CommercialLaunchRiskResource($risk);
    }
}
