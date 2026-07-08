<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AcceptSubscriptionRenewalRiskRequest;
use App\Http\Requests\Api\V1\Admin\CloseSubscriptionRenewalRiskRequest;
use App\Http\Requests\Api\V1\Admin\StoreSubscriptionRenewalRiskRequest;
use App\Http\Requests\Api\V1\Admin\UpdateSubscriptionRenewalRiskRequest;
use App\Http\Resources\Api\V1\Admin\SubscriptionRenewalRiskResource;
use App\Models\AdminAuditLog;
use App\Models\SubscriptionRenewalRisk;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SubscriptionRenewal\SubscriptionRenewalRiskGovernanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 24 — platform-admin subscription renewal risks. Platform admin only. Open
 * CRITICAL/HIGH without a valid accepted risk forces NO-GO; open MEDIUM forces
 * WATCH. Every mutation is audit-logged. No secrets are exposed.
 */
class SubscriptionRenewalRiskController extends Controller
{
    public function __construct(
        private readonly SubscriptionRenewalRiskGovernanceService $risks,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SubscriptionRenewalRisk::query()->latest('id');
        foreach (['severity', 'status', 'area'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return SubscriptionRenewalRiskResource::collection(
            $query->paginate((int) $request->integer('per_page', 20)),
        );
    }

    public function store(StoreSubscriptionRenewalRiskRequest $request): SubscriptionRenewalRiskResource
    {
        $risk = $this->risks->create($request->validated(), $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_RISK_CREATED, $risk);

        return new SubscriptionRenewalRiskResource($risk);
    }

    public function show(SubscriptionRenewalRisk $risk): SubscriptionRenewalRiskResource
    {
        return new SubscriptionRenewalRiskResource($risk);
    }

    public function update(UpdateSubscriptionRenewalRiskRequest $request, SubscriptionRenewalRisk $risk): SubscriptionRenewalRiskResource
    {
        $risk = $this->risks->update($risk, $request->validated(), $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_RISK_UPDATED, $risk);

        return new SubscriptionRenewalRiskResource($risk);
    }

    public function acceptRisk(AcceptSubscriptionRenewalRiskRequest $request, SubscriptionRenewalRisk $risk): SubscriptionRenewalRiskResource
    {
        $data = $request->validated();
        if (isset($data['approver_id'])) {
            $data['approver'] = $data['approver_id'];
        }
        $risk = $this->risks->acceptRisk($risk, $data, $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_RISK_ACCEPTED, $risk);

        return new SubscriptionRenewalRiskResource($risk);
    }

    public function close(CloseSubscriptionRenewalRiskRequest $request, SubscriptionRenewalRisk $risk): SubscriptionRenewalRiskResource
    {
        $risk = $this->risks->close($risk, $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_RISK_CLOSED, $risk);

        return new SubscriptionRenewalRiskResource($risk);
    }

    private function log(Request $request, string $action, SubscriptionRenewalRisk $risk): void
    {
        $this->audit->log(
            actor: $request->user(),
            action: $action,
            targetType: AdminAuditLog::TARGET_SUBSCRIPTION_RENEWAL_RISK,
            targetId: $risk->id,
            after: ['severity' => $risk->severity, 'status' => $risk->status],
            request: $request,
        );
    }
}
