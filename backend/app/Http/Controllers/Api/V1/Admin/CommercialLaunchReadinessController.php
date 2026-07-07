<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\CommercialLaunchReadinessResource;
use App\Services\Commercial\CommercialLaunchReadinessService;

/**
 * Sprint 20 — read-only commercial launch readiness. Platform admin only.
 * Aggregates package/pricing/sales-enablement/onboarding/risk/signoff readiness.
 * No secrets are exposed; nothing is deployed or billed.
 */
class CommercialLaunchReadinessController extends Controller
{
    public function __construct(private readonly CommercialLaunchReadinessService $readiness) {}

    public function index(): CommercialLaunchReadinessResource
    {
        return new CommercialLaunchReadinessResource($this->readiness->evaluate());
    }
}
