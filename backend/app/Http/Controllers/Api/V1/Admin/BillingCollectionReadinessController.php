<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\BillingCollectionReadinessResource;
use App\Services\BillingCollection\BillingCollectionReadinessService;

/**
 * Sprint 23 — read-only billing collection readiness. Platform admin only. Secret-
 * safe PASS/WARN/FAIL report with a GO/WATCH/NO-GO decision.
 */
class BillingCollectionReadinessController extends Controller
{
    public function __construct(
        private readonly BillingCollectionReadinessService $readiness,
    ) {}

    public function index(): BillingCollectionReadinessResource
    {
        return new BillingCollectionReadinessResource($this->readiness->evaluate());
    }
}
