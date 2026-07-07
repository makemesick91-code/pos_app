<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\ProductionHandoverGoNoGoResource;
use App\Services\Handover\ProductionHandoverGoNoGoService;

/**
 * Sprint 18 — read-only aggregated production handover GO/WATCH/NO_GO report for
 * platform admins. Never mutates data, never deploys, never exposes secrets.
 */
class ProductionHandoverGoNoGoController extends Controller
{
    public function index(ProductionHandoverGoNoGoService $service): ProductionHandoverGoNoGoResource
    {
        return new ProductionHandoverGoNoGoResource($service->evaluate());
    }
}
