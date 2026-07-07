<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\PilotStabilizationReportResource;
use App\Services\Pilot\PilotStabilizationReportService;

/**
 * Sprint 17 — read-only aggregated stabilization GO/WATCH/NO-GO report for
 * platform admins. Never mutates data, never prints/exposes secrets.
 */
class PilotStabilizationReportController extends Controller
{
    public function index(PilotStabilizationReportService $service): PilotStabilizationReportResource
    {
        return new PilotStabilizationReportResource($service->evaluate());
    }
}
