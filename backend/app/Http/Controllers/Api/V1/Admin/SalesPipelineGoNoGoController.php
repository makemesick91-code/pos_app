<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\SalesPipelineGoNoGoResource;
use App\Services\SalesPipeline\SalesPipelineGoNoGoService;

/**
 * Sprint 22 — read-only sales pipeline GO/WATCH/NO-GO. Platform admin only.
 * Aggregates prior-sprint gates + sales pipeline readiness. No secrets exposed.
 */
class SalesPipelineGoNoGoController extends Controller
{
    public function __construct(private readonly SalesPipelineGoNoGoService $goNoGo) {}

    public function index(): SalesPipelineGoNoGoResource
    {
        return new SalesPipelineGoNoGoResource($this->goNoGo->evaluate());
    }
}
