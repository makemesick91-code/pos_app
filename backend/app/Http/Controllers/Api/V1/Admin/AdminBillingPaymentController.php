<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\PaymentReasonRequest;
use App\Http\Requests\Api\V1\Admin\RecordInvoicePaymentRequest;
use App\Http\Resources\Api\V1\Admin\TenantBillingPaymentResource;
use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPayment;
use App\Services\Billing\BillingGovernanceException;
use App\Services\Billing\TenantPaymentCollectionService;
use Illuminate\Http\JsonResponse;

/**
 * Sprint 30 — platform-admin payment collection mutations (BIL-R007/R008/R009/
 * R010). Recording is idempotent and never overstates revenue; a failed/cancelled
 * payment never marks the invoice paid. Every mutation is audit-logged; mark
 * failed/cancel require a reason.
 */
class AdminBillingPaymentController extends Controller
{
    public function __construct(
        private readonly TenantPaymentCollectionService $payments,
    ) {}

    public function store(RecordInvoicePaymentRequest $request, TenantBillingInvoice $invoice): JsonResponse
    {
        try {
            $payment = $this->payments->record(
                invoice: $invoice,
                amount: (int) $request->validated('amount'),
                method: (string) ($request->validated('method') ?: 'manual'),
                actor: $request->user(),
                reason: $request->validated('reason'),
                idempotencyKey: $request->validated('idempotency_key') ?: $request->header('Idempotency-Key'),
                source: (string) ($request->validated('source') ?: 'platform_admin'),
                metadata: $request->validated('metadata'),
                request: $request,
            );
        } catch (BillingGovernanceException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->governanceCode,
            ], 422);
        }

        return (new TenantBillingPaymentResource($payment))
            ->response()
            ->setStatusCode($payment->wasRecentlyCreated ? 201 : 200);
    }

    public function markFailed(PaymentReasonRequest $request, TenantBillingPayment $payment): JsonResponse
    {
        try {
            $payment = $this->payments->markFailed($payment, $request->user(), (string) $request->validated('reason'), $request);
        } catch (BillingGovernanceException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->governanceCode], 422);
        }

        return (new TenantBillingPaymentResource($payment))->response()->setStatusCode(200);
    }

    public function cancel(PaymentReasonRequest $request, TenantBillingPayment $payment): JsonResponse
    {
        try {
            $payment = $this->payments->cancel($payment, $request->user(), (string) $request->validated('reason'), $request);
        } catch (BillingGovernanceException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->governanceCode], 422);
        }

        return (new TenantBillingPaymentResource($payment))->response()->setStatusCode(200);
    }
}
