<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillingGovernanceAuditService;
use App\Services\Billing\BillingSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 30 — platform-admin READ-ONLY billing governance visibility (BIL-R007).
 * Collection summary is redacted aggregate counts/amounts; the governance summary
 * surfaces the billing governance audit signals. No mutation, no per-customer PII.
 */
class AdminBillingGovernanceController extends Controller
{
    public function __construct(
        private readonly BillingSummaryService $summaries,
        private readonly BillingGovernanceAuditService $audit,
    ) {}

    public function collectionSummary(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->summaries->collectionSummary(
                $request->query('tenant') ? (int) $request->query('tenant') : null,
                $request->query('period') ? (string) $request->query('period') : null,
            ),
        ]);
    }

    public function governanceSummary(): JsonResponse
    {
        return response()->json(['data' => $this->audit->evaluate()]);
    }
}
