<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\BillingCollectionGoNoGoResource;
use App\Services\BillingCollection\BillingCollectionGoNoGoService;

/**
 * Sprint 23 — read-only billing collection GO/WATCH/NO-GO. Platform admin only.
 * Aggregates the cumulative Sprint 13–22 gates and the full billing collection
 * readiness evaluation. No secrets are exposed.
 */
class BillingCollectionGoNoGoController extends Controller
{
    public function __construct(
        private readonly BillingCollectionGoNoGoService $goNoGo,
    ) {}

    public function index(): BillingCollectionGoNoGoResource
    {
        return new BillingCollectionGoNoGoResource($this->goNoGo->evaluate());
    }
}
