<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreBillingAccountRequest;
use App\Http\Requests\Api\V1\Admin\UpdateBillingAccountRequest;
use App\Http\Resources\Api\V1\Admin\BillingAccountResource;
use App\Models\AdminAuditLog;
use App\Models\SaasBillingAccount;
use App\Services\Admin\AdminAuditLogger;
use App\Services\BillingCollection\BillingAccountService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 23 — platform-admin SaaS billing accounts. Platform admin only. Linking a
 * tenant never creates one; a status change never suspends tenant access. Every
 * mutation is audit-logged. No secrets are exposed.
 */
class BillingAccountController extends Controller
{
    public function __construct(
        private readonly BillingAccountService $accounts,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SaasBillingAccount::query()->latest('id');
        foreach (['status', 'tenant_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return BillingAccountResource::collection(
            $query->paginate((int) $request->integer('per_page', 20)),
        );
    }

    public function store(StoreBillingAccountRequest $request): BillingAccountResource
    {
        $account = $this->accounts->create($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_BILLING_ACCOUNT_CREATED,
            targetType: AdminAuditLog::TARGET_SAAS_BILLING_ACCOUNT,
            targetId: $account->id,
            after: ['status' => $account->status, 'tenant_id' => $account->tenant_id],
            request: $request,
        );

        return new BillingAccountResource($account);
    }

    public function show(SaasBillingAccount $account): BillingAccountResource
    {
        return new BillingAccountResource($account);
    }

    public function update(UpdateBillingAccountRequest $request, SaasBillingAccount $account): BillingAccountResource
    {
        $before = ['status' => $account->status, 'tenant_id' => $account->tenant_id];
        $account = $this->accounts->update($account, $request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_BILLING_ACCOUNT_UPDATED,
            targetType: AdminAuditLog::TARGET_SAAS_BILLING_ACCOUNT,
            targetId: $account->id,
            before: $before,
            after: ['status' => $account->status, 'tenant_id' => $account->tenant_id],
            request: $request,
        );

        return new BillingAccountResource($account);
    }
}
