<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IndexLandingPageVersionRequest;
use App\Http\Requests\Api\V1\Admin\StoreLandingPageVersionRequest;
use App\Http\Requests\Api\V1\Admin\TransitionLandingPageVersionRequest;
use App\Http\Requests\Api\V1\Admin\UpdateLandingPageVersionRequest;
use App\Http\Resources\Api\V1\Admin\LandingPageVersionResource;
use App\Models\AdminAuditLog;
use App\Models\LandingPageVersion;
use App\Services\Admin\AdminAuditLogger;
use App\Services\PublicWebsite\LandingPageContentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 21 — platform-admin landing page versions. Platform admin only. CTA
 * targets are interest-only (validated in the service); package highlights must
 * align with the commercial package catalog. Every mutation is audit-logged.
 */
class LandingPageVersionController extends Controller
{
    public function __construct(
        private readonly LandingPageContentService $service,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(IndexLandingPageVersionRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = LandingPageVersion::query()->latest('id');
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return LandingPageVersionResource::collection(
            $query->paginate((int) ($filters['per_page'] ?? 20)),
        );
    }

    public function store(StoreLandingPageVersionRequest $request): LandingPageVersionResource
    {
        $version = $this->service->create($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_LANDING_VERSION_CREATED,
            targetType: AdminAuditLog::TARGET_LANDING_PAGE_VERSION,
            targetId: $version->id,
            after: ['status' => $version->status],
            request: $request,
        );

        return new LandingPageVersionResource($version);
    }

    public function show(LandingPageVersion $version): LandingPageVersionResource
    {
        return new LandingPageVersionResource($version);
    }

    public function update(UpdateLandingPageVersionRequest $request, LandingPageVersion $version): LandingPageVersionResource
    {
        $before = ['status' => $version->status];
        $version = $this->service->update($version, $request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_LANDING_VERSION_UPDATED,
            targetType: AdminAuditLog::TARGET_LANDING_PAGE_VERSION,
            targetId: $version->id,
            before: $before,
            after: ['status' => $version->status],
            request: $request,
        );

        return new LandingPageVersionResource($version);
    }

    public function approve(TransitionLandingPageVersionRequest $request, LandingPageVersion $version): LandingPageVersionResource
    {
        $version = $this->service->approve($version, $request->user());
        $this->logTransition($request, $version, AdminAuditLog::ACTION_LANDING_VERSION_APPROVED);

        return new LandingPageVersionResource($version);
    }

    public function publish(TransitionLandingPageVersionRequest $request, LandingPageVersion $version): LandingPageVersionResource
    {
        $version = $this->service->publish($version, $request->user());
        $this->logTransition($request, $version, AdminAuditLog::ACTION_LANDING_VERSION_PUBLISHED);

        return new LandingPageVersionResource($version);
    }

    public function archive(TransitionLandingPageVersionRequest $request, LandingPageVersion $version): LandingPageVersionResource
    {
        $version = $this->service->archive($version, $request->user());
        $this->logTransition($request, $version, AdminAuditLog::ACTION_LANDING_VERSION_ARCHIVED);

        return new LandingPageVersionResource($version);
    }

    private function logTransition(TransitionLandingPageVersionRequest $request, LandingPageVersion $version, string $action): void
    {
        $this->audit->log(
            actor: $request->user(),
            action: $action,
            targetType: AdminAuditLog::TARGET_LANDING_PAGE_VERSION,
            targetId: $version->id,
            after: ['status' => $version->status],
            request: $request,
        );
    }
}
