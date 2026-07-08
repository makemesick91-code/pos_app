<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\EvaluateSubscriptionRenewalRunRequest;
use App\Http\Requests\Api\V1\Admin\StoreSubscriptionRenewalRunRequest;
use App\Http\Resources\Api\V1\Admin\SubscriptionRenewalRunResource;
use App\Models\AdminAuditLog;
use App\Models\SubscriptionRenewalRun;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SubscriptionRenewal\SubscriptionRenewalRunService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 24 — platform-admin subscription renewal runs. Platform admin only.
 * Evaluating a run reads existing TenantSubscription records into candidates as
 * awareness only — it never renews, charges, or suspends. Every mutation is
 * audit-logged.
 */
class SubscriptionRenewalRunController extends Controller
{
    public function __construct(
        private readonly SubscriptionRenewalRunService $runs,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SubscriptionRenewalRun::query()->latest('id');
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return SubscriptionRenewalRunResource::collection(
            $query->paginate((int) $request->integer('per_page', 20)),
        );
    }

    public function store(StoreSubscriptionRenewalRunRequest $request): SubscriptionRenewalRunResource
    {
        $run = $this->runs->create($request->validated(), $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_RUN_CREATED, $run);

        return new SubscriptionRenewalRunResource($run);
    }

    public function show(SubscriptionRenewalRun $run): SubscriptionRenewalRunResource
    {
        return new SubscriptionRenewalRunResource($run);
    }

    public function evaluate(EvaluateSubscriptionRenewalRunRequest $request, SubscriptionRenewalRun $run): SubscriptionRenewalRunResource
    {
        $run = $this->runs->evaluate($run, $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_RUN_EVALUATED, $run);

        return new SubscriptionRenewalRunResource($run);
    }

    public function complete(Request $request, SubscriptionRenewalRun $run): SubscriptionRenewalRunResource
    {
        $run = $this->runs->complete($run, $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_RUN_COMPLETED, $run);

        return new SubscriptionRenewalRunResource($run);
    }

    private function log(Request $request, string $action, SubscriptionRenewalRun $run): void
    {
        $this->audit->log(
            actor: $request->user(),
            action: $action,
            targetType: AdminAuditLog::TARGET_SUBSCRIPTION_RENEWAL_RUN,
            targetId: $run->id,
            after: ['status' => $run->status],
            request: $request,
        );
    }
}
