<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\PilotDefectBurnDownResource;
use App\Services\Pilot\DefectBurnDownService;

/**
 * Sprint 17 — read-only defect burn-down summary for platform admins.
 */
class PilotDefectBurnDownController extends Controller
{
    public function index(DefectBurnDownService $service): PilotDefectBurnDownResource
    {
        return new PilotDefectBurnDownResource($service->summary());
    }
}
