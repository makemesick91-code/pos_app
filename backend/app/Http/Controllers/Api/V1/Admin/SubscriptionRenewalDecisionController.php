<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ApplyManualRenewalDecisionRequest;
use App\Http\Requests\Api\V1\Admin\StoreSubscriptionRenewalDecisionRequest;
use App\Http\Requests\Api\V1\Admin\VoidSubscriptionRenewalDecisionRequest;
use App\Http\Resources\Api\V1\Admin\SubscriptionRenewalDecisionResource;
use App\Models\AdminAuditLog;
use App\Models\SubscriptionRenewalCandidate;
use App\Models\SubscriptionRenewalDecision;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SubscriptionRenewal\SubscriptionRenewalDecisionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 24 — platform-admin subscription renewal decisions. Platform admin only.
 * Recording a decision never mutates a TenantSubscription and payment evidence
 * never auto-renews. The explicit apply-manual-renewal action is the only
 * subscription-mutating path; it is audit-logged and never triggered
 * automatically.
 */
class SubscriptionRenewalDecisionController extends Controller
{
    public function __construct(
        private readonly SubscriptionRenewalDecisionService $decisions,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(Request $request, SubscriptionRenewalCandidate $candidate): AnonymousResourceCollection
    {
        return SubscriptionRenewalDecisionResource::collection(
            $candidate->decisions()->latest('id')->paginate((int) $request->integer('per_page', 20)),
        );
    }

    public function store(StoreSubscriptionRenewalDecisionRequest $request, SubscriptionRenewalCandidate $candidate): SubscriptionRenewalDecisionResource
    {
        $decision = $this->decisions->record($candidate, $request->validated(), $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_DECISION_RECORDED, $decision);

        return new SubscriptionRenewalDecisionResource($decision);
    }

    public function void(VoidSubscriptionRenewalDecisionRequest $request, SubscriptionRenewalDecision $decision): SubscriptionRenewalDecisionResource
    {
        $decision = $this->decisions->void($decision, $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_DECISION_VOIDED, $decision);

        return new SubscriptionRenewalDecisionResource($decision);
    }

    /**
     * Explicit, admin-only, audit-logged manual renewal apply. This is the only
     * subscription-mutating endpoint in Sprint 24. The service enforces the
     * RECORDED + APPROVE_MANUAL_RENEWAL/APPROVE_WITH_RISK + decider + effective-date
     * guardrails. It is never triggered automatically or by payment evidence.
     */
    public function applyManualRenewal(ApplyManualRenewalDecisionRequest $request, SubscriptionRenewalDecision $decision): SubscriptionRenewalDecisionResource
    {
        $decision = $this->decisions->applyManualRenewalDecision($decision, $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_DECISION_APPLIED_MANUALLY, $decision);

        return new SubscriptionRenewalDecisionResource($decision);
    }

    private function log(Request $request, string $action, SubscriptionRenewalDecision $decision): void
    {
        $this->audit->log(
            actor: $request->user(),
            action: $action,
            targetType: AdminAuditLog::TARGET_SUBSCRIPTION_RENEWAL_DECISION,
            targetId: $decision->id,
            after: ['decision' => $decision->decision, 'status' => $decision->status],
            request: $request,
        );
    }
}
