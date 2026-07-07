<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StorePublicWebsiteSignoffRequest;
use App\Http\Resources\Api\V1\Admin\PublicWebsiteSignoffResource;
use App\Models\AdminAuditLog;
use App\Models\PublicWebsiteSignoff;
use App\Services\Admin\AdminAuditLogger;
use App\Services\PublicWebsite\PublicWebsiteReadinessService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 21 — platform-admin public website signoffs. Platform admin only. A
 * REJECTED signoff forces NO-GO; APPROVED_WITH_RISK forces WATCH. Records are
 * preserved and audit-logged. No secrets are exposed.
 */
class PublicWebsiteSignoffController extends Controller
{
    public function __construct(
        private readonly PublicWebsiteReadinessService $service,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return PublicWebsiteSignoffResource::collection(
            PublicWebsiteSignoff::query()->latest('id')->paginate(20),
        );
    }

    public function store(StorePublicWebsiteSignoffRequest $request): PublicWebsiteSignoffResource
    {
        $signoff = $this->service->addSignoff($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_WEBSITE_SIGNOFF_ADDED,
            targetType: AdminAuditLog::TARGET_PUBLIC_WEBSITE_SIGNOFF,
            targetId: $signoff->id,
            after: ['signer_role' => $signoff->signer_role, 'decision' => $signoff->decision],
            request: $request,
        );

        return new PublicWebsiteSignoffResource($signoff);
    }
}
