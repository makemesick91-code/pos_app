<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreSalesLeadActivityRequest;
use App\Http\Requests\Api\V1\Admin\TransitionSalesLeadActivityRequest;
use App\Http\Resources\Api\V1\Admin\SalesLeadActivityResource;
use App\Models\AdminAuditLog;
use App\Models\SalesLead;
use App\Models\SalesLeadActivity;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SalesPipeline\SalesLeadActivityService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 22 — platform-admin sales lead activities. Platform admin only.
 * WHATSAPP_MANUAL / EMAIL_MANUAL activities are manual notes only — no real
 * message is ever sent. Every mutation is audit-logged. No secrets are exposed.
 */
class SalesLeadActivityController extends Controller
{
    public function __construct(
        private readonly SalesLeadActivityService $activities,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(SalesLead $lead): AnonymousResourceCollection
    {
        return SalesLeadActivityResource::collection(
            $lead->activities()->latest('id')->paginate(20),
        );
    }

    public function store(StoreSalesLeadActivityRequest $request, SalesLead $lead): SalesLeadActivityResource
    {
        $activity = $this->activities->add($lead, $request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_ACTIVITY_CREATED,
            targetType: AdminAuditLog::TARGET_SALES_LEAD_ACTIVITY,
            targetId: $activity->id,
            after: ['activity_type' => $activity->activity_type, 'status' => $activity->status],
            request: $request,
        );

        return new SalesLeadActivityResource($activity);
    }

    public function complete(TransitionSalesLeadActivityRequest $request, SalesLead $lead, SalesLeadActivity $activity): SalesLeadActivityResource
    {
        $activity = $this->activities->complete($activity, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_ACTIVITY_COMPLETED,
            targetType: AdminAuditLog::TARGET_SALES_LEAD_ACTIVITY,
            targetId: $activity->id,
            after: ['status' => $activity->status],
            request: $request,
        );

        return new SalesLeadActivityResource($activity);
    }

    public function cancel(TransitionSalesLeadActivityRequest $request, SalesLead $lead, SalesLeadActivity $activity): SalesLeadActivityResource
    {
        $activity = $this->activities->cancel($activity, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_ACTIVITY_CANCELLED,
            targetType: AdminAuditLog::TARGET_SALES_LEAD_ACTIVITY,
            targetId: $activity->id,
            after: ['status' => $activity->status],
            request: $request,
        );

        return new SalesLeadActivityResource($activity);
    }
}
