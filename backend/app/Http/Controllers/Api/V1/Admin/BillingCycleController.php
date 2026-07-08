<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreBillingCycleRequest;
use App\Http\Requests\Api\V1\Admin\UpdateBillingCycleRequest;
use App\Http\Resources\Api\V1\Admin\BillingCycleResource;
use App\Models\AdminAuditLog;
use App\Models\SaasBillingCycle;
use App\Services\Admin\AdminAuditLogger;
use App\Services\BillingCollection\BillingCycleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 23 — platform-admin SaaS billing cycles. Platform admin only. Conservative
 * DRAFT → OPEN → LOCKED → CLOSED transitions. Every mutation is audit-logged.
 */
class BillingCycleController extends Controller
{
    public function __construct(
        private readonly BillingCycleService $cycles,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SaasBillingCycle::query()->latest('id');
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return BillingCycleResource::collection(
            $query->paginate((int) $request->integer('per_page', 20)),
        );
    }

    public function store(StoreBillingCycleRequest $request): BillingCycleResource
    {
        $cycle = $this->cycles->create($request->validated(), $request->user());
        $this->logCycle($request, AdminAuditLog::ACTION_BILLING_CYCLE_CREATED, $cycle);

        return new BillingCycleResource($cycle);
    }

    public function update(UpdateBillingCycleRequest $request, SaasBillingCycle $cycle): BillingCycleResource
    {
        $cycle = $this->cycles->update($cycle, $request->validated(), $request->user());
        $this->logCycle($request, AdminAuditLog::ACTION_BILLING_CYCLE_UPDATED, $cycle);

        return new BillingCycleResource($cycle);
    }

    public function open(Request $request, SaasBillingCycle $cycle): BillingCycleResource
    {
        $cycle = $this->cycles->open($cycle);
        $this->logCycle($request, AdminAuditLog::ACTION_BILLING_CYCLE_TRANSITIONED, $cycle);

        return new BillingCycleResource($cycle);
    }

    public function lock(Request $request, SaasBillingCycle $cycle): BillingCycleResource
    {
        $cycle = $this->cycles->lock($cycle);
        $this->logCycle($request, AdminAuditLog::ACTION_BILLING_CYCLE_TRANSITIONED, $cycle);

        return new BillingCycleResource($cycle);
    }

    public function close(Request $request, SaasBillingCycle $cycle): BillingCycleResource
    {
        $cycle = $this->cycles->close($cycle);
        $this->logCycle($request, AdminAuditLog::ACTION_BILLING_CYCLE_TRANSITIONED, $cycle);

        return new BillingCycleResource($cycle);
    }

    private function logCycle(Request $request, string $action, SaasBillingCycle $cycle): void
    {
        $this->audit->log(
            actor: $request->user(),
            action: $action,
            targetType: AdminAuditLog::TARGET_SAAS_BILLING_CYCLE,
            targetId: $cycle->id,
            after: ['status' => $cycle->status],
            request: $request,
        );
    }
}
