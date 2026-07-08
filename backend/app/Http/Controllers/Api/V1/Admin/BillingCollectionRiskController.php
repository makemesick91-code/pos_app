<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AcceptBillingCollectionRiskRequest;
use App\Http\Requests\Api\V1\Admin\CloseBillingCollectionRiskRequest;
use App\Http\Requests\Api\V1\Admin\StoreBillingCollectionRiskRequest;
use App\Http\Requests\Api\V1\Admin\UpdateBillingCollectionRiskRequest;
use App\Http\Resources\Api\V1\Admin\BillingCollectionRiskResource;
use App\Models\AdminAuditLog;
use App\Models\SaasBillingCollectionRisk;
use App\Services\Admin\AdminAuditLogger;
use App\Services\BillingCollection\BillingCollectionRiskGovernanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 23 — platform-admin SaaS billing collection risks. Platform admin only.
 * Open CRITICAL/HIGH without a valid accepted risk forces NO-GO; open MEDIUM forces
 * WATCH. Every mutation is audit-logged. No secrets are exposed.
 */
class BillingCollectionRiskController extends Controller
{
    public function __construct(
        private readonly BillingCollectionRiskGovernanceService $risks,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SaasBillingCollectionRisk::query()->latest('id');
        foreach (['severity', 'status', 'area'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return BillingCollectionRiskResource::collection(
            $query->paginate((int) $request->integer('per_page', 20)),
        );
    }

    public function store(StoreBillingCollectionRiskRequest $request): BillingCollectionRiskResource
    {
        $risk = $this->risks->create($request->validated(), $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_RISK_CREATED, $risk);

        return new BillingCollectionRiskResource($risk);
    }

    public function show(SaasBillingCollectionRisk $risk): BillingCollectionRiskResource
    {
        return new BillingCollectionRiskResource($risk);
    }

    public function update(UpdateBillingCollectionRiskRequest $request, SaasBillingCollectionRisk $risk): BillingCollectionRiskResource
    {
        $risk = $this->risks->update($risk, $request->validated(), $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_RISK_UPDATED, $risk);

        return new BillingCollectionRiskResource($risk);
    }

    public function acceptRisk(AcceptBillingCollectionRiskRequest $request, SaasBillingCollectionRisk $risk): BillingCollectionRiskResource
    {
        $data = $request->validated();
        if (isset($data['approver_id'])) {
            $data['approver'] = $data['approver_id'];
        }
        $risk = $this->risks->acceptRisk($risk, $data, $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_RISK_ACCEPTED, $risk);

        return new BillingCollectionRiskResource($risk);
    }

    public function close(CloseBillingCollectionRiskRequest $request, SaasBillingCollectionRisk $risk): BillingCollectionRiskResource
    {
        $risk = $this->risks->close($risk, $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_RISK_CLOSED, $risk);

        return new BillingCollectionRiskResource($risk);
    }

    private function log(Request $request, string $action, SaasBillingCollectionRisk $risk): void
    {
        $this->audit->log(
            actor: $request->user(),
            action: $action,
            targetType: AdminAuditLog::TARGET_SAAS_BILLING_COLLECTION_RISK,
            targetId: $risk->id,
            after: ['severity' => $risk->severity, 'status' => $risk->status],
            request: $request,
        );
    }
}
