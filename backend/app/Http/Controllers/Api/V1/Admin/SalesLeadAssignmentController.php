<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AssignSalesLeadRequest;
use App\Http\Resources\Api\V1\Admin\SalesLeadAssignmentResource;
use App\Http\Resources\Api\V1\Admin\SalesLeadResource;
use App\Models\AdminAuditLog;
use App\Models\SalesLead;
use App\Models\SalesLeadActivity;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SalesPipeline\SalesLeadActivityService;
use App\Services\SalesPipeline\SalesLeadAssignmentService;

/**
 * Sprint 22 — platform-admin sales lead assignment. Platform admin only. Internal
 * sales ownership only — never provisions anything. Audit-logged. No secrets.
 */
class SalesLeadAssignmentController extends Controller
{
    public function __construct(
        private readonly SalesLeadAssignmentService $assignments,
        private readonly SalesLeadActivityService $activities,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function assign(AssignSalesLeadRequest $request, SalesLead $lead): SalesLeadAssignmentResource
    {
        $assignment = $this->assignments->assign($lead, $request->validated(), $request->user());
        $this->activities->record($lead, SalesLeadActivity::TYPE_ASSIGNMENT, 'Lead assigned', $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_LEAD_ASSIGNED,
            targetType: AdminAuditLog::TARGET_SALES_LEAD_ASSIGNMENT,
            targetId: $assignment->id,
            after: ['assigned_to_user_id' => $assignment->assigned_to_user_id],
            request: $request,
        );

        return new SalesLeadAssignmentResource($assignment);
    }

    public function unassign(\Illuminate\Http\Request $request, SalesLead $lead): SalesLeadResource
    {
        $this->assignments->unassign($lead, $request->user(), $request->input('reason'));
        $this->activities->record($lead, SalesLeadActivity::TYPE_ASSIGNMENT, 'Lead unassigned', $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_LEAD_UNASSIGNED,
            targetType: AdminAuditLog::TARGET_SALES_LEAD,
            targetId: $lead->id,
            after: ['assigned_to_user_id' => null],
            request: $request,
        );

        return new SalesLeadResource($lead->refresh());
    }
}
