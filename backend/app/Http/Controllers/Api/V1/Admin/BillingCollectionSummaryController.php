<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\BillingCollectionSummaryResource;
use App\Services\BillingCollection\BillingCollectionActivityService;
use App\Services\BillingCollection\BillingPaymentEvidenceService;

/**
 * Sprint 23 — read-only billing collection summary. Platform admin only. Combines
 * manual collection activity and manual payment evidence into a secret-safe view.
 * Manual follow-up only; no real message sending.
 */
class BillingCollectionSummaryController extends Controller
{
    public function __construct(
        private readonly BillingCollectionActivityService $activities,
        private readonly BillingPaymentEvidenceService $evidences,
    ) {}

    public function index(): BillingCollectionSummaryResource
    {
        $activity = $this->activities->summary();
        $evidence = $this->evidences->summary();

        return new BillingCollectionSummaryResource([
            'decision' => 'GO',
            'activities_by_type' => $activity['by_type'],
            'activities_by_status' => $activity['by_status'],
            'payment_evidence_by_status' => $evidence['by_status'],
            'manual_follow_up_only' => true,
            'no_real_sending' => true,
            'no_payment_gateway_call' => true,
        ]);
    }
}
