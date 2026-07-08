<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IndexSalesLeadRequest;
use App\Http\Requests\Api\V1\Admin\MarkSalesLeadLostRequest;
use App\Http\Requests\Api\V1\Admin\QualifySalesLeadRequest;
use App\Http\Requests\Api\V1\Admin\StoreSalesLeadRequest;
use App\Http\Requests\Api\V1\Admin\TransitionSalesLeadRequest;
use App\Http\Requests\Api\V1\Admin\UpdateSalesLeadRequest;
use App\Http\Resources\Api\V1\Admin\SalesLeadResource;
use App\Models\AdminAuditLog;
use App\Models\LeadInterestSubmission;
use App\Models\SalesLead;
use App\Models\SalesLeadActivity;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SalesPipeline\SalesLeadActivityService;
use App\Services\SalesPipeline\SalesLeadIntakeService;
use App\Services\SalesPipeline\SalesPipelineStageService;
use App\Services\SalesPipeline\SalesQualificationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 22 — platform-admin sales leads. Platform admin only. A lead may be
 * imported from a Sprint 21 lead interest submission but NEVER creates a
 * tenant/user/subscription/device and NEVER bills. Every mutation is audit-logged.
 * No secrets are exposed.
 */
class SalesLeadController extends Controller
{
    public function __construct(
        private readonly SalesLeadIntakeService $intake,
        private readonly SalesPipelineStageService $stages,
        private readonly SalesQualificationService $qualification,
        private readonly SalesLeadActivityService $activities,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(IndexSalesLeadRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = SalesLead::query()->latest('id');
        foreach (['status', 'priority', 'source', 'pipeline_stage_id', 'assigned_to_user_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('lead_reference', 'like', "%{$search}%");
            });
        }

        return SalesLeadResource::collection($query->paginate((int) ($filters['per_page'] ?? 20)));
    }

    public function store(StoreSalesLeadRequest $request): SalesLeadResource
    {
        $lead = $this->intake->create($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_LEAD_CREATED,
            targetType: AdminAuditLog::TARGET_SALES_LEAD,
            targetId: $lead->id,
            after: ['status' => $lead->status, 'source' => $lead->source],
            request: $request,
        );

        return new SalesLeadResource($lead);
    }

    public function show(SalesLead $lead): SalesLeadResource
    {
        return new SalesLeadResource($lead);
    }

    public function update(UpdateSalesLeadRequest $request, SalesLead $lead): SalesLeadResource
    {
        $before = ['status' => $lead->status, 'priority' => $lead->priority];
        $lead = $this->intake->update($lead, $request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_LEAD_UPDATED,
            targetType: AdminAuditLog::TARGET_SALES_LEAD,
            targetId: $lead->id,
            before: $before,
            after: ['status' => $lead->status, 'priority' => $lead->priority],
            request: $request,
        );

        return new SalesLeadResource($lead);
    }

    public function importInterest(\Illuminate\Http\Request $request, LeadInterestSubmission $leadInterestSubmission): SalesLeadResource
    {
        $lead = $this->intake->importFromInterest($leadInterestSubmission, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_LEAD_IMPORTED,
            targetType: AdminAuditLog::TARGET_SALES_LEAD,
            targetId: $lead->id,
            after: ['lead_interest_submission_id' => $leadInterestSubmission->id],
            request: $request,
        );

        return new SalesLeadResource($lead);
    }

    public function transition(TransitionSalesLeadRequest $request, SalesLead $lead): SalesLeadResource
    {
        $data = $request->validated();
        $before = ['stage_id' => $lead->pipeline_stage_id, 'status' => $lead->status];
        $lead = $this->stages->transitionLead($lead, (string) $data['stage_code'], $request->user());

        $this->activities->record(
            $lead,
            SalesLeadActivity::TYPE_STATUS_CHANGE,
            'Stage → '.$data['stage_code'],
            $request->user(),
            $data['note'] ?? null,
        );

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_LEAD_TRANSITIONED,
            targetType: AdminAuditLog::TARGET_SALES_LEAD,
            targetId: $lead->id,
            before: $before,
            after: ['stage_id' => $lead->pipeline_stage_id, 'status' => $lead->status],
            request: $request,
        );

        return new SalesLeadResource($lead);
    }

    public function qualify(QualifySalesLeadRequest $request, SalesLead $lead): SalesLeadResource
    {
        $lead = $this->qualification->markQualified($lead, $request->user());
        $this->activities->record($lead, SalesLeadActivity::TYPE_QUALIFICATION, 'Lead qualified', $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_LEAD_QUALIFIED,
            targetType: AdminAuditLog::TARGET_SALES_LEAD,
            targetId: $lead->id,
            after: ['status' => $lead->status, 'qualification_score' => $lead->qualification_score],
            request: $request,
        );

        return new SalesLeadResource($lead);
    }

    public function markLost(MarkSalesLeadLostRequest $request, SalesLead $lead): SalesLeadResource
    {
        $lead = $this->qualification->markLost($lead, $request->validated()['reason'] ?? null, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_LEAD_LOST,
            targetType: AdminAuditLog::TARGET_SALES_LEAD,
            targetId: $lead->id,
            after: ['status' => $lead->status],
            request: $request,
        );

        return new SalesLeadResource($lead);
    }

    public function readyForOnboarding(\Illuminate\Http\Request $request, SalesLead $lead): SalesLeadResource
    {
        $lead = $this->qualification->markReadyForOnboarding($lead, $request->user());
        $this->activities->record($lead, SalesLeadActivity::TYPE_ONBOARDING_HANDOVER_REVIEW, 'Ready for onboarding review', $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_LEAD_READY_FOR_ONBOARDING,
            targetType: AdminAuditLog::TARGET_SALES_LEAD,
            targetId: $lead->id,
            after: ['status' => $lead->status, 'ready_for_onboarding_at' => (string) $lead->ready_for_onboarding_at],
            request: $request,
        );

        return new SalesLeadResource($lead);
    }
}
