<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\SalesPipelineLeadSummaryResource;
use App\Services\SalesPipeline\SalesLeadIntakeService;

/**
 * Sprint 22 — read-only sales lead summary. Platform admin only. Leads are
 * intake-only; no provisioning, no billing, no secrets.
 */
class SalesPipelineLeadSummaryController extends Controller
{
    public function __construct(private readonly SalesLeadIntakeService $leads) {}

    public function index(): SalesPipelineLeadSummaryResource
    {
        return new SalesPipelineLeadSummaryResource($this->leads->summary());
    }
}
