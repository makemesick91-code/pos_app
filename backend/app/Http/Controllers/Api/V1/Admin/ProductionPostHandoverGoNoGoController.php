<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\PostHandoverGovernanceReportResource;
use App\Services\Operations\PostHandoverGovernanceReportService;

/**
 * Sprint 19 — read-only post-handover production operations GO/WATCH/NO_GO.
 * Platform admin only (platform.admin middleware). Aggregates every prior gate
 * plus operations governance into a single decision. No secrets are exposed;
 * never deploys, never runs real backup/restore, never sends real alerts.
 */
class ProductionPostHandoverGoNoGoController extends Controller
{
    public function __construct(
        private readonly PostHandoverGovernanceReportService $report,
    ) {}

    public function index(): PostHandoverGovernanceReportResource
    {
        return new PostHandoverGovernanceReportResource($this->report->evaluate());
    }
}
