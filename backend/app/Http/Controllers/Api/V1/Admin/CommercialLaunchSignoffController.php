<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCommercialLaunchSignoffRequest;
use App\Http\Resources\Api\V1\Admin\CommercialLaunchSignoffResource;
use App\Models\AdminAuditLog;
use App\Models\CommercialLaunchRun;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Commercial\CommercialLaunchReadinessService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 20 — platform-admin commercial launch signoffs. Platform admin only. A
 * REJECTED signoff forces the launch NO-GO; an APPROVED_WITH_RISK signoff forces
 * WATCH. Signoffs are preserved and audit-logged. No secrets are exposed.
 */
class CommercialLaunchSignoffController extends Controller
{
    public function __construct(
        private readonly CommercialLaunchReadinessService $readiness,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(CommercialLaunchRun $launchRun): AnonymousResourceCollection
    {
        return CommercialLaunchSignoffResource::collection(
            $launchRun->signoffs()->latest('id')->get(),
        );
    }

    public function store(StoreCommercialLaunchSignoffRequest $request, CommercialLaunchRun $launchRun): CommercialLaunchSignoffResource
    {
        $signoff = $this->readiness->addSignoff($launchRun, $request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_LAUNCH_SIGNOFF_ADDED,
            targetType: AdminAuditLog::TARGET_COMMERCIAL_LAUNCH_SIGNOFF,
            targetId: $signoff->id,
            after: ['signer_role' => $signoff->signer_role, 'decision' => $signoff->decision],
            request: $request,
        );

        return new CommercialLaunchSignoffResource($signoff);
    }
}
