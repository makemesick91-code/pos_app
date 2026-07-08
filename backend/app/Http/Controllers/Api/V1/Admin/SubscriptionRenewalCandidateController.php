<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\TransitionSubscriptionRenewalCandidateRequest;
use App\Http\Requests\Api\V1\Admin\UpdateSubscriptionRenewalCandidateRequest;
use App\Http\Resources\Api\V1\Admin\SubscriptionRenewalCandidateResource;
use App\Models\AdminAuditLog;
use App\Models\SubscriptionRenewalCandidate;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SubscriptionRenewal\SubscriptionRenewalCandidateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 24 — platform-admin subscription renewal candidates. Platform admin only.
 * No transition mutates a TenantSubscription; READY_FOR_MANUAL_RENEWAL only flags
 * that an admin decision is required. Every mutation is audit-logged.
 */
class SubscriptionRenewalCandidateController extends Controller
{
    public function __construct(
        private readonly SubscriptionRenewalCandidateService $candidates,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SubscriptionRenewalCandidate::query()->latest('id');
        foreach (['status', 'renewal_stage', 'priority', 'tenant_id', 'run_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return SubscriptionRenewalCandidateResource::collection(
            $query->paginate((int) $request->integer('per_page', 20)),
        );
    }

    public function show(SubscriptionRenewalCandidate $candidate): SubscriptionRenewalCandidateResource
    {
        return new SubscriptionRenewalCandidateResource($candidate);
    }

    public function update(UpdateSubscriptionRenewalCandidateRequest $request, SubscriptionRenewalCandidate $candidate): SubscriptionRenewalCandidateResource
    {
        $candidate = $this->candidates->update($candidate, $request->validated(), $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_CANDIDATE_UPDATED, $candidate);

        return new SubscriptionRenewalCandidateResource($candidate);
    }

    public function readyForManualRenewal(TransitionSubscriptionRenewalCandidateRequest $request, SubscriptionRenewalCandidate $candidate): SubscriptionRenewalCandidateResource
    {
        $candidate = $this->candidates->markReadyForManualRenewal($candidate, $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_CANDIDATE_READY, $candidate);

        return new SubscriptionRenewalCandidateResource($candidate);
    }

    public function graceReview(TransitionSubscriptionRenewalCandidateRequest $request, SubscriptionRenewalCandidate $candidate): SubscriptionRenewalCandidateResource
    {
        $candidate = $this->candidates->markGraceReview($candidate, $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_CANDIDATE_GRACE_REVIEW, $candidate);

        return new SubscriptionRenewalCandidateResource($candidate);
    }

    public function overdueReview(TransitionSubscriptionRenewalCandidateRequest $request, SubscriptionRenewalCandidate $candidate): SubscriptionRenewalCandidateResource
    {
        $candidate = $this->candidates->markOverdueReview($candidate, $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_CANDIDATE_OVERDUE_REVIEW, $candidate);

        return new SubscriptionRenewalCandidateResource($candidate);
    }

    public function doNotRenew(TransitionSubscriptionRenewalCandidateRequest $request, SubscriptionRenewalCandidate $candidate): SubscriptionRenewalCandidateResource
    {
        $candidate = $this->candidates->markDoNotRenew($candidate, $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_CANDIDATE_DO_NOT_RENEW, $candidate);

        return new SubscriptionRenewalCandidateResource($candidate);
    }

    private function log(Request $request, string $action, SubscriptionRenewalCandidate $candidate): void
    {
        $this->audit->log(
            actor: $request->user(),
            action: $action,
            targetType: AdminAuditLog::TARGET_SUBSCRIPTION_RENEWAL_CANDIDATE,
            targetId: $candidate->id,
            after: ['status' => $candidate->status, 'renewal_stage' => $candidate->renewal_stage],
            request: $request,
        );
    }
}
