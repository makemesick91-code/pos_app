<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\RejectBillingPaymentEvidenceRequest;
use App\Http\Requests\Api\V1\Admin\ReviewBillingPaymentEvidenceRequest;
use App\Http\Requests\Api\V1\Admin\StoreBillingPaymentEvidenceRequest;
use App\Http\Resources\Api\V1\Admin\BillingPaymentEvidenceResource;
use App\Models\AdminAuditLog;
use App\Models\SaasBillingInvoice;
use App\Models\SaasBillingPaymentEvidence;
use App\Services\Admin\AdminAuditLogger;
use App\Services\BillingCollection\BillingPaymentEvidenceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 23 — platform-admin SaaS billing manual payment evidence. Platform admin
 * only. Manual evidence ONLY: MANUAL_QRIS_REFERENCE is a label, never a QRIS/payment
 * gateway call. Accepting applies the amount to the invoice through service
 * governance; a rejected evidence never updates paid_amount; a voided invoice never
 * receives evidence. Every mutation is audit-logged.
 */
class BillingPaymentEvidenceController extends Controller
{
    public function __construct(
        private readonly BillingPaymentEvidenceService $evidences,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(Request $request, SaasBillingInvoice $invoice): AnonymousResourceCollection
    {
        return BillingPaymentEvidenceResource::collection(
            $invoice->paymentEvidences()->latest('id')->paginate((int) $request->integer('per_page', 20)),
        );
    }

    public function store(StoreBillingPaymentEvidenceRequest $request, SaasBillingInvoice $invoice): BillingPaymentEvidenceResource
    {
        $evidence = $this->evidences->submit($invoice, $request->validated(), $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_PAYMENT_EVIDENCE_SUBMITTED, $evidence);

        return new BillingPaymentEvidenceResource($evidence);
    }

    public function underReview(ReviewBillingPaymentEvidenceRequest $request, SaasBillingPaymentEvidence $paymentEvidence): BillingPaymentEvidenceResource
    {
        $paymentEvidence = $this->evidences->underReview($paymentEvidence, $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_PAYMENT_EVIDENCE_UNDER_REVIEW, $paymentEvidence);

        return new BillingPaymentEvidenceResource($paymentEvidence);
    }

    public function accept(ReviewBillingPaymentEvidenceRequest $request, SaasBillingPaymentEvidence $paymentEvidence): BillingPaymentEvidenceResource
    {
        $paymentEvidence = $this->evidences->accept($paymentEvidence, $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_PAYMENT_EVIDENCE_ACCEPTED, $paymentEvidence);

        return new BillingPaymentEvidenceResource($paymentEvidence);
    }

    public function reject(RejectBillingPaymentEvidenceRequest $request, SaasBillingPaymentEvidence $paymentEvidence): BillingPaymentEvidenceResource
    {
        $paymentEvidence = $this->evidences->reject($paymentEvidence, (string) $request->validated('reason'), $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_PAYMENT_EVIDENCE_REJECTED, $paymentEvidence);

        return new BillingPaymentEvidenceResource($paymentEvidence);
    }

    public function void(Request $request, SaasBillingPaymentEvidence $paymentEvidence): BillingPaymentEvidenceResource
    {
        $paymentEvidence = $this->evidences->void($paymentEvidence, $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_PAYMENT_EVIDENCE_VOIDED, $paymentEvidence);

        return new BillingPaymentEvidenceResource($paymentEvidence);
    }

    private function log(Request $request, string $action, SaasBillingPaymentEvidence $evidence): void
    {
        $this->audit->log(
            actor: $request->user(),
            action: $action,
            targetType: AdminAuditLog::TARGET_SAAS_BILLING_PAYMENT_EVIDENCE,
            targetId: $evidence->id,
            after: ['status' => $evidence->status, 'invoice_id' => $evidence->invoice_id],
            request: $request,
        );
    }
}
