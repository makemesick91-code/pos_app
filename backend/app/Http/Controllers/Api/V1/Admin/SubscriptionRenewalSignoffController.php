<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreSubscriptionRenewalSignoffRequest;
use App\Http\Resources\Api\V1\Admin\SubscriptionRenewalSignoffResource;
use App\Models\AdminAuditLog;
use App\Models\SubscriptionRenewalSignoff;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SubscriptionRenewal\SubscriptionRenewalReadinessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 24 — platform-admin subscription renewal sign-offs. Platform admin only.
 * A rejected sign-off forces NO-GO; an approved-with-risk sign-off forces WATCH.
 * Every mutation is audit-logged.
 */
class SubscriptionRenewalSignoffController extends Controller
{
    public function __construct(
        private readonly SubscriptionRenewalReadinessService $readiness,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SubscriptionRenewalSignoff::query()->latest('id');
        foreach (['signer_role', 'decision'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return SubscriptionRenewalSignoffResource::collection(
            $query->paginate((int) $request->integer('per_page', 20)),
        );
    }

    public function store(StoreSubscriptionRenewalSignoffRequest $request): SubscriptionRenewalSignoffResource
    {
        $signoff = $this->readiness->addSignoff($request->validated(), $request->user());
        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_RENEWAL_SIGNOFF_ADDED,
            targetType: AdminAuditLog::TARGET_SUBSCRIPTION_RENEWAL_SIGNOFF,
            targetId: $signoff->id,
            after: ['signer_role' => $signoff->signer_role, 'decision' => $signoff->decision],
            request: $request,
        );

        return new SubscriptionRenewalSignoffResource($signoff);
    }
}
