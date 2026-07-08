<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreBillingCollectionActivityRequest;
use App\Http\Requests\Api\V1\Admin\TransitionBillingCollectionActivityRequest;
use App\Http\Resources\Api\V1\Admin\BillingCollectionActivityResource;
use App\Models\AdminAuditLog;
use App\Models\SaasBillingCollectionActivity;
use App\Services\Admin\AdminAuditLogger;
use App\Services\BillingCollection\BillingCollectionActivityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 23 — platform-admin SaaS billing collection activities. Platform admin
 * only. WHATSAPP_MANUAL / EMAIL_MANUAL are notes only — no real message is ever
 * sent. Every mutation is audit-logged.
 */
class BillingCollectionActivityController extends Controller
{
    public function __construct(
        private readonly BillingCollectionActivityService $activities,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SaasBillingCollectionActivity::query()->latest('id');
        foreach (['activity_type', 'status', 'billing_account_id', 'invoice_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return BillingCollectionActivityResource::collection(
            $query->paginate((int) $request->integer('per_page', 20)),
        );
    }

    public function store(StoreBillingCollectionActivityRequest $request): BillingCollectionActivityResource
    {
        $activity = $this->activities->create($request->validated(), $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_ACTIVITY_CREATED, $activity);

        return new BillingCollectionActivityResource($activity);
    }

    public function complete(TransitionBillingCollectionActivityRequest $request, SaasBillingCollectionActivity $activity): BillingCollectionActivityResource
    {
        $activity = $this->activities->complete($activity, $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_ACTIVITY_COMPLETED, $activity);

        return new BillingCollectionActivityResource($activity);
    }

    public function cancel(TransitionBillingCollectionActivityRequest $request, SaasBillingCollectionActivity $activity): BillingCollectionActivityResource
    {
        $activity = $this->activities->cancel($activity, $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_ACTIVITY_CANCELLED, $activity);

        return new BillingCollectionActivityResource($activity);
    }

    private function log(Request $request, string $action, SaasBillingCollectionActivity $activity): void
    {
        $this->audit->log(
            actor: $request->user(),
            action: $action,
            targetType: AdminAuditLog::TARGET_SAAS_BILLING_COLLECTION_ACTIVITY,
            targetId: $activity->id,
            after: ['activity_type' => $activity->activity_type, 'status' => $activity->status],
            request: $request,
        );
    }
}
