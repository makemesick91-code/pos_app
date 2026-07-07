<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\ProductionIncidentSummaryResource;
use App\Services\Operations\ProductionIncidentService;

/**
 * Sprint 19 — read-only production incident summary. Platform admin only
 * (platform.admin middleware). Returns open counts by severity/status/area/SLA
 * and the GO/WATCH/NO_GO decision. No secrets are exposed.
 */
class ProductionIncidentSummaryController extends Controller
{
    public function __construct(
        private readonly ProductionIncidentService $incidents,
    ) {}

    public function index(): ProductionIncidentSummaryResource
    {
        return new ProductionIncidentSummaryResource($this->incidents->summary());
    }
}
