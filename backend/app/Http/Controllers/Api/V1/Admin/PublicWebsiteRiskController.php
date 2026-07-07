<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AcceptPublicWebsiteRiskRequest;
use App\Http\Requests\Api\V1\Admin\ClosePublicWebsiteRiskRequest;
use App\Http\Requests\Api\V1\Admin\IndexPublicWebsiteRiskRequest;
use App\Http\Requests\Api\V1\Admin\StorePublicWebsiteRiskRequest;
use App\Http\Requests\Api\V1\Admin\UpdatePublicWebsiteRiskRequest;
use App\Http\Resources\Api\V1\Admin\PublicWebsiteRiskResource;
use App\Models\AdminAuditLog;
use App\Models\PublicWebsiteRisk;
use App\Services\Admin\AdminAuditLogger;
use App\Services\PublicWebsite\PublicWebsiteRiskGovernanceService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 21 — platform-admin public website risks. Platform admin only. Open
 * CRITICAL/HIGH without a valid accepted risk forces NO-GO; open MEDIUM forces
 * WATCH. Every mutation is audit-logged. No secrets are exposed.
 */
class PublicWebsiteRiskController extends Controller
{
    public function __construct(
        private readonly PublicWebsiteRiskGovernanceService $risks,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(IndexPublicWebsiteRiskRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = PublicWebsiteRisk::query()->latest('id');
        foreach (['severity', 'status', 'area'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return PublicWebsiteRiskResource::collection(
            $query->paginate((int) ($filters['per_page'] ?? 20)),
        );
    }

    public function store(StorePublicWebsiteRiskRequest $request): PublicWebsiteRiskResource
    {
        $risk = $this->risks->create($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_WEBSITE_RISK_CREATED,
            targetType: AdminAuditLog::TARGET_PUBLIC_WEBSITE_RISK,
            targetId: $risk->id,
            after: ['severity' => $risk->severity, 'status' => $risk->status],
            request: $request,
        );

        return new PublicWebsiteRiskResource($risk);
    }

    public function show(PublicWebsiteRisk $risk): PublicWebsiteRiskResource
    {
        return new PublicWebsiteRiskResource($risk);
    }

    public function update(UpdatePublicWebsiteRiskRequest $request, PublicWebsiteRisk $risk): PublicWebsiteRiskResource
    {
        $before = ['severity' => $risk->severity, 'status' => $risk->status];
        $risk = $this->risks->update($risk, $request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_WEBSITE_RISK_UPDATED,
            targetType: AdminAuditLog::TARGET_PUBLIC_WEBSITE_RISK,
            targetId: $risk->id,
            before: $before,
            after: ['severity' => $risk->severity, 'status' => $risk->status],
            request: $request,
        );

        return new PublicWebsiteRiskResource($risk);
    }

    public function acceptRisk(AcceptPublicWebsiteRiskRequest $request, PublicWebsiteRisk $risk): PublicWebsiteRiskResource
    {
        $data = $request->validated();
        if (isset($data['approver_id'])) {
            $data['approver'] = $data['approver_id'];
        }
        $before = ['status' => $risk->status];
        $risk = $this->risks->acceptRisk($risk, $data, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_WEBSITE_RISK_ACCEPTED,
            targetType: AdminAuditLog::TARGET_PUBLIC_WEBSITE_RISK,
            targetId: $risk->id,
            before: $before,
            after: ['status' => $risk->status],
            request: $request,
        );

        return new PublicWebsiteRiskResource($risk);
    }

    public function close(ClosePublicWebsiteRiskRequest $request, PublicWebsiteRisk $risk): PublicWebsiteRiskResource
    {
        $before = ['status' => $risk->status];
        $risk = $this->risks->close($risk, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_WEBSITE_RISK_CLOSED,
            targetType: AdminAuditLog::TARGET_PUBLIC_WEBSITE_RISK,
            targetId: $risk->id,
            before: $before,
            after: ['status' => $risk->status],
            request: $request,
        );

        return new PublicWebsiteRiskResource($risk);
    }
}
