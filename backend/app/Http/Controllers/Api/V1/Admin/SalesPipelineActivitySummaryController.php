<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\SalesPipelineActivitySummaryResource;
use App\Services\SalesPipeline\SalesLeadActivityService;

/**
 * Sprint 22 — read-only sales activity summary. Platform admin only. Manual
 * follow-up only; no real message sending, no secrets.
 */
class SalesPipelineActivitySummaryController extends Controller
{
    public function __construct(private readonly SalesLeadActivityService $activities) {}

    public function index(): SalesPipelineActivitySummaryResource
    {
        return new SalesPipelineActivitySummaryResource($this->activities->summary());
    }
}
