<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\PaymentGateway\PaymentGatewayGovernanceAuditService;
use App\Services\PaymentGateway\PaymentGatewaySummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 31 — platform-admin READ-ONLY gateway governance visibility (PGW-R014/
 * R016). Provider posture, settlement aggregates, and the governance audit
 * signals. No mutation, no secret/credential value, no per-customer PII.
 */
class AdminPaymentGatewayGovernanceController extends Controller
{
    public function __construct(
        private readonly PaymentGatewaySummaryService $summaries,
        private readonly PaymentGatewayGovernanceAuditService $audit,
    ) {}

    public function providerSummary(): JsonResponse
    {
        return response()->json(['data' => $this->summaries->providerSummary()]);
    }

    public function settlementSummary(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant') ? (int) $request->query('tenant') : null;

        return response()->json(['data' => [
            'intents' => $this->summaries->intentSummary($tenantId),
            'events' => $this->summaries->eventSummary($tenantId),
            'settlements' => $this->summaries->settlementSummary($tenantId),
        ]]);
    }

    public function governanceSummary(): JsonResponse
    {
        return response()->json(['data' => $this->audit->evaluate()]);
    }
}
