<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\SubscriptionRenewalReadinessResource;
use App\Services\SubscriptionRenewal\SubscriptionRenewalReadinessService;

/**
 * Sprint 24 — read-only subscription renewal readiness. Platform admin only.
 * Secret-safe PASS/WARN/FAIL report with a GO/WATCH/NO-GO decision.
 */
class SubscriptionRenewalReadinessController extends Controller
{
    public function __construct(
        private readonly SubscriptionRenewalReadinessService $readiness,
    ) {}

    public function index(): SubscriptionRenewalReadinessResource
    {
        return new SubscriptionRenewalReadinessResource($this->readiness->evaluate());
    }
}
