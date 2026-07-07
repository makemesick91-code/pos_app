<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IndexLeadInterestSubmissionRequest;
use App\Http\Requests\Api\V1\Admin\TransitionLeadInterestSubmissionRequest;
use App\Http\Resources\Api\V1\Admin\LeadInterestSubmissionResource;
use App\Models\AdminAuditLog;
use App\Models\LeadInterestSubmission;
use App\Services\Admin\AdminAuditLogger;
use App\Services\PublicWebsite\LeadInterestGovernanceService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 21 — platform-admin lead interest submissions. Platform admin only.
 * Read + status change only. A lead status change never provisions a tenant/user/
 * subscription/device and never sends a real email/WhatsApp. Mutations are
 * audit-logged.
 */
class LeadInterestSubmissionController extends Controller
{
    public function __construct(
        private readonly LeadInterestGovernanceService $service,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(IndexLeadInterestSubmissionRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = LeadInterestSubmission::query()->latest('id');
        foreach (['status', 'source'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return LeadInterestSubmissionResource::collection(
            $query->paginate((int) ($filters['per_page'] ?? 20)),
        );
    }

    public function show(LeadInterestSubmission $lead): LeadInterestSubmissionResource
    {
        return new LeadInterestSubmissionResource($lead);
    }

    public function status(TransitionLeadInterestSubmissionRequest $request, LeadInterestSubmission $lead): LeadInterestSubmissionResource
    {
        $before = ['status' => $lead->status];
        $lead = $this->service->changeStatus($lead, (string) $request->validated()['status'], $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_LEAD_STATUS_CHANGED,
            targetType: AdminAuditLog::TARGET_LEAD_INTEREST_SUBMISSION,
            targetId: $lead->id,
            before: $before,
            after: ['status' => $lead->status],
            request: $request,
        );

        return new LeadInterestSubmissionResource($lead);
    }
}
