<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreSalesPipelineSignoffRequest;
use App\Http\Resources\Api\V1\Admin\SalesPipelineSignoffResource;
use App\Models\AdminAuditLog;
use App\Models\SalesPipelineSignoff;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SalesPipeline\SalesPipelineReadinessService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 22 — platform-admin sales pipeline sign-offs. Platform admin only. A
 * REJECTED signoff forces NO-GO; APPROVED_WITH_RISK forces WATCH. Records are
 * preserved and audit-logged. No secrets are exposed.
 */
class SalesPipelineSignoffController extends Controller
{
    public function __construct(
        private readonly SalesPipelineReadinessService $service,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return SalesPipelineSignoffResource::collection(
            SalesPipelineSignoff::query()->latest('id')->paginate(20),
        );
    }

    public function store(StoreSalesPipelineSignoffRequest $request): SalesPipelineSignoffResource
    {
        $signoff = $this->service->addSignoff($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_SALES_SIGNOFF_ADDED,
            targetType: AdminAuditLog::TARGET_SALES_PIPELINE_SIGNOFF,
            targetId: $signoff->id,
            after: ['signer_role' => $signoff->signer_role, 'decision' => $signoff->decision],
            request: $request,
        );

        return new SalesPipelineSignoffResource($signoff);
    }
}
