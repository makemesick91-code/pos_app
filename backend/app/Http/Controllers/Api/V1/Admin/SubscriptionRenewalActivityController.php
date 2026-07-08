<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreSubscriptionRenewalActivityRequest;
use App\Http\Requests\Api\V1\Admin\TransitionSubscriptionRenewalActivityRequest;
use App\Http\Resources\Api\V1\Admin\SubscriptionRenewalActivityResource;
use App\Models\AdminAuditLog;
use App\Models\SubscriptionRenewalActivity;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SubscriptionRenewal\SubscriptionRenewalActivityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 24 — platform-admin subscription renewal activities. Platform admin only.
 * A manual WhatsApp/email activity is only an internal record — no real message is
 * sent. Every mutation is audit-logged.
 */
class SubscriptionRenewalActivityController extends Controller
{
    public function __construct(
        private readonly SubscriptionRenewalActivityService $activities,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SubscriptionRenewalActivity::query()->latest('id');
        foreach (['activity_type', 'status', 'candidate_id', 'tenant_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return SubscriptionRenewalActivityResource::collection(
            $query->paginate((int) $request->integer('per_page', 20)),
        );
    }

    public function store(StoreSubscriptionRenewalActivityRequest $request): SubscriptionRenewalActivityResource
    {
        $activity = $this->activities->record($request->validated(), $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_ACTIVITY_CREATED, $activity);

        return new SubscriptionRenewalActivityResource($activity);
    }

    public function complete(TransitionSubscriptionRenewalActivityRequest $request, SubscriptionRenewalActivity $activity): SubscriptionRenewalActivityResource
    {
        $activity = $this->activities->complete($activity, $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_ACTIVITY_COMPLETED, $activity);

        return new SubscriptionRenewalActivityResource($activity);
    }

    public function cancel(TransitionSubscriptionRenewalActivityRequest $request, SubscriptionRenewalActivity $activity): SubscriptionRenewalActivityResource
    {
        $activity = $this->activities->cancel($activity, $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_ACTIVITY_CANCELLED, $activity);

        return new SubscriptionRenewalActivityResource($activity);
    }

    private function log(Request $request, string $action, SubscriptionRenewalActivity $activity): void
    {
        $this->audit->log(
            actor: $request->user(),
            action: $action,
            targetType: AdminAuditLog::TARGET_SUBSCRIPTION_RENEWAL_ACTIVITY,
            targetId: $activity->id,
            after: ['activity_type' => $activity->activity_type, 'status' => $activity->status],
            request: $request,
        );
    }
}
