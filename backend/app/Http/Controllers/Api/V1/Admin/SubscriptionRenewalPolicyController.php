<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreSubscriptionRenewalPolicyRequest;
use App\Http\Requests\Api\V1\Admin\UpdateSubscriptionRenewalPolicyRequest;
use App\Http\Resources\Api\V1\Admin\SubscriptionRenewalPolicyResource;
use App\Models\AdminAuditLog;
use App\Models\SubscriptionRenewalPolicy;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SubscriptionRenewal\SubscriptionRenewalPolicyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 24 — platform-admin subscription renewal policies. Platform admin only.
 * A policy is governance metadata; it never triggers real sending, auto-charge, or
 * auto-suspension. Every mutation is audit-logged. No secrets are exposed.
 */
class SubscriptionRenewalPolicyController extends Controller
{
    public function __construct(
        private readonly SubscriptionRenewalPolicyService $policies,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SubscriptionRenewalPolicy::query()->latest('id');
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return SubscriptionRenewalPolicyResource::collection(
            $query->paginate((int) $request->integer('per_page', 20)),
        );
    }

    public function store(StoreSubscriptionRenewalPolicyRequest $request): SubscriptionRenewalPolicyResource
    {
        $policy = $this->policies->create($request->validated(), $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_POLICY_CREATED, $policy);

        return new SubscriptionRenewalPolicyResource($policy);
    }

    public function show(SubscriptionRenewalPolicy $policy): SubscriptionRenewalPolicyResource
    {
        return new SubscriptionRenewalPolicyResource($policy);
    }

    public function update(UpdateSubscriptionRenewalPolicyRequest $request, SubscriptionRenewalPolicy $policy): SubscriptionRenewalPolicyResource
    {
        $policy = $this->policies->update($policy, $request->validated(), $request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_POLICY_UPDATED, $policy);

        return new SubscriptionRenewalPolicyResource($policy);
    }

    public function ensureDefault(Request $request): SubscriptionRenewalPolicyResource
    {
        $policy = $this->policies->ensureDefault($request->user());
        $this->log($request, AdminAuditLog::ACTION_RENEWAL_POLICY_DEFAULT_ENSURED, $policy);

        return new SubscriptionRenewalPolicyResource($policy);
    }

    private function log(Request $request, string $action, SubscriptionRenewalPolicy $policy): void
    {
        $this->audit->log(
            actor: $request->user(),
            action: $action,
            targetType: AdminAuditLog::TARGET_SUBSCRIPTION_RENEWAL_POLICY,
            targetId: $policy->id,
            after: ['code' => $policy->code, 'status' => $policy->status],
            request: $request,
        );
    }
}
