<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\CommercialOnboardingCapacityResource;
use App\Services\Commercial\OnboardingCapacityService;

/**
 * Sprint 20 — read-only commercial onboarding capacity. Platform admin only.
 * Aggregate placeholders only; never creates real tenants or uses real customer
 * data.
 */
class CommercialOnboardingCapacityController extends Controller
{
    public function __construct(private readonly OnboardingCapacityService $onboarding) {}

    public function index(): CommercialOnboardingCapacityResource
    {
        return new CommercialOnboardingCapacityResource($this->onboarding->evaluate());
    }
}
