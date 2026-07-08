<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\SubscriptionRenewalGoNoGoResource;
use App\Services\SubscriptionRenewal\SubscriptionRenewalGoNoGoService;

/**
 * Sprint 24 — read-only subscription renewal GO/WATCH/NO-GO aggregation. Platform
 * admin only. Aggregates the Sprint 13–23 gate contract and the subscription
 * renewal readiness evaluation. Secret-safe; never charges, deploys, or sends.
 */
class SubscriptionRenewalGoNoGoController extends Controller
{
    public function __construct(
        private readonly SubscriptionRenewalGoNoGoService $goNoGo,
    ) {}

    public function index(): SubscriptionRenewalGoNoGoResource
    {
        return new SubscriptionRenewalGoNoGoResource($this->goNoGo->evaluate());
    }
}
