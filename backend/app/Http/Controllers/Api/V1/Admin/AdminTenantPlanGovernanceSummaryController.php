<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\TenantPlanGovernanceSummaryResource;
use App\Services\TenantPlan\TenantPlanSummaryService;

/**
 * Sprint 26 — read-only tenant plan governance summary. Platform admin only.
 * Secret-safe counts of plans, registries, assignments, and overrides.
 */
class AdminTenantPlanGovernanceSummaryController extends Controller
{
    public function __construct(
        private readonly TenantPlanSummaryService $summary,
    ) {}

    public function show(): TenantPlanGovernanceSummaryResource
    {
        return new TenantPlanGovernanceSummaryResource($this->summary->governanceSummary());
    }
}
