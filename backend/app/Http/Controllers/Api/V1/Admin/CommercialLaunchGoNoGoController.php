<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\CommercialLaunchGoNoGoResource;
use App\Services\Commercial\CommercialLaunchGoNoGoService;

/**
 * Sprint 20 — read-only commercial launch GO/WATCH/NO-GO. Platform admin only.
 * Aggregates the cumulative prior-sprint gate contract and the commercial launch
 * readiness into a single decision. No secrets are exposed; nothing is deployed,
 * billed, or signed up publicly.
 */
class CommercialLaunchGoNoGoController extends Controller
{
    public function __construct(private readonly CommercialLaunchGoNoGoService $goNoGo) {}

    public function index(): CommercialLaunchGoNoGoResource
    {
        return new CommercialLaunchGoNoGoResource($this->goNoGo->evaluate());
    }
}
