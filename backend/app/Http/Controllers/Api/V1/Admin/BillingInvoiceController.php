<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IssueBillingInvoiceRequest;
use App\Http\Requests\Api\V1\Admin\MarkBillingInvoiceDisputedRequest;
use App\Http\Requests\Api\V1\Admin\StoreBillingInvoiceRequest;
use App\Http\Requests\Api\V1\Admin\UpdateBillingInvoiceRequest;
use App\Http\Requests\Api\V1\Admin\VoidBillingInvoiceRequest;
use App\Http\Resources\Api\V1\Admin\BillingInvoiceResource;
use App\Models\AdminAuditLog;
use App\Models\SaasBillingAccount;
use App\Models\SaasBillingInvoice;
use App\Services\Admin\AdminAuditLogger;
use App\Services\BillingCollection\BillingInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 23 — platform-admin SaaS billing invoices. Platform admin only. Totals are
 * server-calculated from lines. Issuing never triggers a payment gateway and never
 * auto-suspends a tenant; paid/remaining are only mutated by payment evidence
 * review. Every mutation is audit-logged. No secrets are exposed.
 */
class BillingInvoiceController extends Controller
{
    public function __construct(
        private readonly BillingInvoiceService $invoices,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SaasBillingInvoice::query()->latest('id');
        foreach (['status', 'billing_account_id', 'billing_cycle_id', 'tenant_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return BillingInvoiceResource::collection(
            $query->paginate((int) $request->integer('per_page', 20)),
        );
    }

    public function store(StoreBillingInvoiceRequest $request): BillingInvoiceResource
    {
        $data = $request->validated();
        $account = SaasBillingAccount::query()->findOrFail($data['billing_account_id']);
        $invoice = $this->invoices->createDraft($account, $data, $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_INVOICE_CREATED, $invoice);

        return new BillingInvoiceResource($invoice->load('lines'));
    }

    public function show(SaasBillingInvoice $invoice): BillingInvoiceResource
    {
        return new BillingInvoiceResource($invoice->load('lines'));
    }

    public function update(UpdateBillingInvoiceRequest $request, SaasBillingInvoice $invoice): BillingInvoiceResource
    {
        $invoice = $this->invoices->updateInvoice($invoice, $request->validated(), $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_INVOICE_UPDATED, $invoice);

        return new BillingInvoiceResource($invoice->load('lines'));
    }

    public function issue(IssueBillingInvoiceRequest $request, SaasBillingInvoice $invoice): BillingInvoiceResource
    {
        $invoice = $this->invoices->issue($invoice, $request->validated(), $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_INVOICE_ISSUED, $invoice);

        return new BillingInvoiceResource($invoice->load('lines'));
    }

    public function markOverdue(Request $request, SaasBillingInvoice $invoice): BillingInvoiceResource
    {
        $invoice = $this->invoices->markOverdue($invoice, $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_INVOICE_OVERDUE, $invoice);

        return new BillingInvoiceResource($invoice->load('lines'));
    }

    public function markDisputed(MarkBillingInvoiceDisputedRequest $request, SaasBillingInvoice $invoice): BillingInvoiceResource
    {
        $invoice = $this->invoices->markDisputed($invoice, $request->validated(), $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_INVOICE_DISPUTED, $invoice);

        return new BillingInvoiceResource($invoice->load('lines'));
    }

    public function void(VoidBillingInvoiceRequest $request, SaasBillingInvoice $invoice): BillingInvoiceResource
    {
        $invoice = $this->invoices->void($invoice, $request->validated(), $request->user());
        $this->log($request, AdminAuditLog::ACTION_BILLING_INVOICE_VOIDED, $invoice);

        return new BillingInvoiceResource($invoice->load('lines'));
    }

    private function log(Request $request, string $action, SaasBillingInvoice $invoice): void
    {
        $this->audit->log(
            actor: $request->user(),
            action: $action,
            targetType: AdminAuditLog::TARGET_SAAS_BILLING_INVOICE,
            targetId: $invoice->id,
            after: ['status' => $invoice->status, 'total_amount' => (string) $invoice->total_amount],
            request: $request,
        );
    }
}
