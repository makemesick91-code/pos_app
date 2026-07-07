<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ApprovePilotClosureRequest;
use App\Http\Requests\Api\V1\Admin\BlockPilotClosureRequest;
use App\Http\Requests\Api\V1\Admin\IndexPilotClosureRequest;
use App\Http\Requests\Api\V1\Admin\StorePilotClosureRequest;
use App\Http\Resources\Api\V1\Admin\PilotClosureResource;
use App\Models\AdminAuditLog;
use App\Models\PilotClosureRun;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Handover\PilotClosureService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 18 — platform-admin pilot closure runs. Platform admin only
 * (platform.admin middleware); tenant business users are blocked. Closure
 * summaries are computed from the defect/accepted-risk/stabilization review.
 * Every action is recorded to the admin audit log. No secrets are exposed.
 */
class PilotClosureController extends Controller
{
    public function __construct(
        private readonly PilotClosureService $closure,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(IndexPilotClosureRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = PilotClosureRun::query()->latest('id');
        foreach (['status', 'decision'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return PilotClosureResource::collection(
            $query->paginate((int) ($filters['per_page'] ?? 20)),
        );
    }

    public function store(StorePilotClosureRequest $request): PilotClosureResource
    {
        $run = $this->closure->create($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_CLOSURE_CREATED,
            targetType: AdminAuditLog::TARGET_PILOT_CLOSURE_RUN,
            targetId: $run->id,
            after: ['status' => $run->status, 'decision' => $run->decision],
            request: $request,
        );

        return new PilotClosureResource($run);
    }

    public function show(PilotClosureRun $closure): PilotClosureResource
    {
        return new PilotClosureResource($closure);
    }

    public function approve(ApprovePilotClosureRequest $request, PilotClosureRun $closure): PilotClosureResource
    {
        $before = $closure->status;
        $closure = $this->closure->approve($closure, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_CLOSURE_APPROVED,
            targetType: AdminAuditLog::TARGET_PILOT_CLOSURE_RUN,
            targetId: $closure->id,
            before: ['status' => $before],
            after: ['status' => $closure->status],
            request: $request,
        );

        return new PilotClosureResource($closure);
    }

    public function block(BlockPilotClosureRequest $request, PilotClosureRun $closure): PilotClosureResource
    {
        $before = $closure->status;
        $closure = $this->closure->block($closure, $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_CLOSURE_BLOCKED,
            targetType: AdminAuditLog::TARGET_PILOT_CLOSURE_RUN,
            targetId: $closure->id,
            before: ['status' => $before],
            after: ['status' => $closure->status],
            request: $request,
        );

        return new PilotClosureResource($closure);
    }
}
