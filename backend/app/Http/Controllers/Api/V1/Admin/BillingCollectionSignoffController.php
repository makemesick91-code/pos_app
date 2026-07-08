<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreBillingCollectionSignoffRequest;
use App\Http\Resources\Api\V1\Admin\BillingCollectionSignoffResource;
use App\Models\AdminAuditLog;
use App\Models\SaasBillingCollectionSignoff;
use App\Services\Admin\AdminAuditLogger;
use App\Services\BillingCollection\BillingCollectionReadinessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 23 — platform-admin SaaS billing collection sign-offs. Platform admin
 * only. Append-only; a REJECTED sign-off forces NO-GO, an APPROVED_WITH_RISK
 * sign-off forces WATCH. Every mutation is audit-logged.
 */
class BillingCollectionSignoffController extends Controller
{
    public function __construct(
        private readonly BillingCollectionReadinessService $readiness,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SaasBillingCollectionSignoff::query()->latest('id');
        foreach (['signer_role', 'decision'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return BillingCollectionSignoffResource::collection(
            $query->paginate((int) $request->integer('per_page', 20)),
        );
    }

    public function store(StoreBillingCollectionSignoffRequest $request): BillingCollectionSignoffResource
    {
        $signoff = $this->readiness->addSignoff($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_BILLING_SIGNOFF_ADDED,
            targetType: AdminAuditLog::TARGET_SAAS_BILLING_COLLECTION_SIGNOFF,
            targetId: $signoff->id,
            after: ['signer_role' => $signoff->signer_role, 'decision' => $signoff->decision],
            request: $request,
        );

        return new BillingCollectionSignoffResource($signoff);
    }
}
