<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IndexPublicWebsitePageRequest;
use App\Http\Requests\Api\V1\Admin\StorePublicWebsitePageRequest;
use App\Http\Requests\Api\V1\Admin\TransitionPublicWebsitePageRequest;
use App\Http\Requests\Api\V1\Admin\UpdatePublicWebsitePageRequest;
use App\Http\Resources\Api\V1\Admin\PublicWebsitePageResource;
use App\Models\AdminAuditLog;
use App\Models\PublicWebsitePage;
use App\Services\Admin\AdminAuditLogger;
use App\Services\PublicWebsite\PublicWebsiteReadinessService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 21 — platform-admin public website pages. Platform admin only. Page
 * content is governance metadata; no secrets, no admin URLs, no tenant creation.
 * Every mutation is audit-logged.
 */
class PublicWebsitePageController extends Controller
{
    public function __construct(
        private readonly PublicWebsiteReadinessService $service,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(IndexPublicWebsitePageRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = PublicWebsitePage::query()->latest('id');
        foreach (['status', 'page_key'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return PublicWebsitePageResource::collection(
            $query->paginate((int) ($filters['per_page'] ?? 20)),
        );
    }

    public function store(StorePublicWebsitePageRequest $request): PublicWebsitePageResource
    {
        $page = $this->service->createPage($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_WEBSITE_PAGE_CREATED,
            targetType: AdminAuditLog::TARGET_PUBLIC_WEBSITE_PAGE,
            targetId: $page->id,
            after: ['page_key' => $page->page_key, 'status' => $page->status],
            request: $request,
        );

        return new PublicWebsitePageResource($page);
    }

    public function show(PublicWebsitePage $page): PublicWebsitePageResource
    {
        return new PublicWebsitePageResource($page);
    }

    public function update(UpdatePublicWebsitePageRequest $request, PublicWebsitePage $page): PublicWebsitePageResource
    {
        $before = ['status' => $page->status];
        $page = $this->service->updatePage($page, $request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_WEBSITE_PAGE_UPDATED,
            targetType: AdminAuditLog::TARGET_PUBLIC_WEBSITE_PAGE,
            targetId: $page->id,
            before: $before,
            after: ['status' => $page->status],
            request: $request,
        );

        return new PublicWebsitePageResource($page);
    }

    public function approve(TransitionPublicWebsitePageRequest $request, PublicWebsitePage $page): PublicWebsitePageResource
    {
        $page = $this->service->approvePage($page, $request->user());
        $this->logTransition($request, $page, AdminAuditLog::ACTION_WEBSITE_PAGE_APPROVED);

        return new PublicWebsitePageResource($page);
    }

    public function publish(TransitionPublicWebsitePageRequest $request, PublicWebsitePage $page): PublicWebsitePageResource
    {
        $page = $this->service->publishPage($page, $request->user());
        $this->logTransition($request, $page, AdminAuditLog::ACTION_WEBSITE_PAGE_PUBLISHED);

        return new PublicWebsitePageResource($page);
    }

    public function archive(TransitionPublicWebsitePageRequest $request, PublicWebsitePage $page): PublicWebsitePageResource
    {
        $page = $this->service->archivePage($page, $request->user());
        $this->logTransition($request, $page, AdminAuditLog::ACTION_WEBSITE_PAGE_ARCHIVED);

        return new PublicWebsitePageResource($page);
    }

    private function logTransition(TransitionPublicWebsitePageRequest $request, PublicWebsitePage $page, string $action): void
    {
        $this->audit->log(
            actor: $request->user(),
            action: $action,
            targetType: AdminAuditLog::TARGET_PUBLIC_WEBSITE_PAGE,
            targetId: $page->id,
            after: ['status' => $page->status],
            request: $request,
        );
    }
}
