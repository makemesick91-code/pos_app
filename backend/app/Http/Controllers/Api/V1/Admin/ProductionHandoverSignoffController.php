<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreProductionHandoverSignoffRequest;
use App\Http\Resources\Api\V1\Admin\ProductionHandoverSignoffResource;
use App\Models\AdminAuditLog;
use App\Models\ProductionHandoverPackage;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Handover\ProductionSignoffService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 18 — append-only production handover sign-offs. Platform admin only.
 * Sign-off records are never mutated or deleted; a REJECTED decision forces
 * NO_GO and APPROVED_WITH_RISK forces WATCH downstream. Every sign-off is
 * recorded to the admin audit log.
 */
class ProductionHandoverSignoffController extends Controller
{
    public function __construct(
        private readonly ProductionSignoffService $signoffs,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(ProductionHandoverPackage $handover): AnonymousResourceCollection
    {
        return ProductionHandoverSignoffResource::collection(
            $handover->signoffs()->orderBy('id')->get(),
        );
    }

    public function store(StoreProductionHandoverSignoffRequest $request, ProductionHandoverPackage $handover): ProductionHandoverSignoffResource
    {
        $signoff = $this->signoffs->addSignoff($handover, $request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_HANDOVER_SIGNOFF_ADDED,
            targetType: AdminAuditLog::TARGET_PRODUCTION_HANDOVER_SIGNOFF,
            targetId: $signoff->id,
            after: ['signer_role' => $signoff->signer_role, 'decision' => $signoff->decision],
            request: $request,
        );

        return new ProductionHandoverSignoffResource($signoff);
    }
}
