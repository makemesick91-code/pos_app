<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\SalesPipelineReadinessResource;
use App\Services\SalesPipeline\SalesPipelineReadinessService;

/**
 * Sprint 22 — read-only sales pipeline readiness. Platform admin only. Aggregates
 * stages/lead/assignment/activity/qualification/onboarding/risk/signoff/docs
 * readiness. No secrets exposed.
 */
class SalesPipelineReadinessController extends Controller
{
    public function __construct(private readonly SalesPipelineReadinessService $readiness) {}

    public function index(): SalesPipelineReadinessResource
    {
        return new SalesPipelineReadinessResource($this->readiness->evaluate());
    }
}
