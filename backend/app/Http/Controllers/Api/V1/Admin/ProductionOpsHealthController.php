<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\ProductionOpsHealthResource;
use App\Services\Operations\ProductionOperationsHealthService;

/**
 * Sprint 19 — read-only production operations health. Platform admin only
 * (platform.admin middleware). Returns the aggregate health signals and the
 * GO/WATCH/NO_GO decision. No secrets are exposed; never deploys, never runs
 * real backup/restore, never sends real alerts.
 */
class ProductionOpsHealthController extends Controller
{
    public function __construct(
        private readonly ProductionOperationsHealthService $health,
    ) {}

    public function index(): ProductionOpsHealthResource
    {
        return new ProductionOpsHealthResource($this->health->evaluate());
    }
}
