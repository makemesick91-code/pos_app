<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ApproveCommercialLaunchRunRequest;
use App\Http\Requests\Api\V1\Admin\BlockCommercialLaunchRunRequest;
use App\Http\Requests\Api\V1\Admin\IndexCommercialLaunchRunRequest;
use App\Http\Requests\Api\V1\Admin\StoreCommercialLaunchRunRequest;
use App\Http\Resources\Api\V1\Admin\CommercialLaunchRunResource;
use App\Models\AdminAuditLog;
use App\Models\CommercialLaunchRun;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Commercial\CommercialLaunchReadinessService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 20 — platform-admin commercial launch runs. Platform admin only
 * (platform.admin middleware); tenant business users are blocked. A run records
 * the evidence-backed commercial launch readiness evaluation. Every action is
 * recorded to the admin audit log. No secrets are exposed; recording a run never
 * deploys, never bills a real customer, and never opens public signup.
 */
class CommercialLaunchRunController extends Controller
{
    public function __construct(
        private readonly CommercialLaunchReadinessService $readiness,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(IndexCommercialLaunchRunRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = CommercialLaunchRun::query()->latest('id');
        foreach (['status', 'decision'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return CommercialLaunchRunResource::collection(
            $query->paginate((int) ($filters['per_page'] ?? 20)),
        );
    }

    public function store(StoreCommercialLaunchRunRequest $request): CommercialLaunchRunResource
    {
        $run = $this->readiness->createRun($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_LAUNCH_RUN_CREATED,
            targetType: AdminAuditLog::TARGET_COMMERCIAL_LAUNCH_RUN,
            targetId: $run->id,
            after: ['status' => $run->status, 'decision' => $run->decision],
            request: $request,
        );

        return new CommercialLaunchRunResource($run);
    }

    public function show(CommercialLaunchRun $launchRun): CommercialLaunchRunResource
    {
        return new CommercialLaunchRunResource($launchRun);
    }

    public function approve(ApproveCommercialLaunchRunRequest $request, CommercialLaunchRun $launchRun): CommercialLaunchRunResource
    {
        $before = $launchRun->status;
        $launchRun = $this->readiness->approve($launchRun, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_LAUNCH_RUN_APPROVED,
            targetType: AdminAuditLog::TARGET_COMMERCIAL_LAUNCH_RUN,
            targetId: $launchRun->id,
            before: ['status' => $before],
            after: ['status' => $launchRun->status],
            request: $request,
        );

        return new CommercialLaunchRunResource($launchRun);
    }

    public function block(BlockCommercialLaunchRunRequest $request, CommercialLaunchRun $launchRun): CommercialLaunchRunResource
    {
        $before = $launchRun->status;
        $launchRun = $this->readiness->block($launchRun, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_LAUNCH_RUN_BLOCKED,
            targetType: AdminAuditLog::TARGET_COMMERCIAL_LAUNCH_RUN,
            targetId: $launchRun->id,
            before: ['status' => $before],
            after: ['status' => $launchRun->status],
            request: $request,
        );

        return new CommercialLaunchRunResource($launchRun);
    }
}
