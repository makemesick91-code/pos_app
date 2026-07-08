<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\SubscriptionRenewalCandidateSummaryResource;
use App\Services\SubscriptionRenewal\SubscriptionRenewalCandidateService;

/**
 * Sprint 24 — read-only subscription renewal candidate summary. Platform admin
 * only. Secret-safe counts by status/stage/priority.
 */
class SubscriptionRenewalCandidateSummaryController extends Controller
{
    public function __construct(
        private readonly SubscriptionRenewalCandidateService $candidates,
    ) {}

    public function index(): SubscriptionRenewalCandidateSummaryResource
    {
        return new SubscriptionRenewalCandidateSummaryResource($this->candidates->summary());
    }
}
