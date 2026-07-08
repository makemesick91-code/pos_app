<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreBillingInvoiceLineRequest;
use App\Http\Requests\Api\V1\Admin\UpdateBillingInvoiceLineRequest;
use App\Http\Resources\Api\V1\Admin\BillingInvoiceLineResource;
use App\Models\AdminAuditLog;
use App\Models\SaasBillingInvoice;
use App\Models\SaasBillingInvoiceLine;
use App\Services\Admin\AdminAuditLogger;
use App\Services\BillingCollection\BillingInvoiceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Sprint 23 — platform-admin SaaS billing invoice lines. Platform admin only. Lines
 * can only be added/edited on a DRAFT invoice; line_total and invoice totals are
 * recalculated server-side. Every mutation is audit-logged.
 */
class BillingInvoiceLineController extends Controller
{
    public function __construct(
        private readonly BillingInvoiceService $invoices,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function store(StoreBillingInvoiceLineRequest $request, SaasBillingInvoice $invoice): BillingInvoiceLineResource
    {
        $line = $this->invoices->addLine($invoice, $request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_BILLING_INVOICE_LINE_ADDED,
            targetType: AdminAuditLog::TARGET_SAAS_BILLING_INVOICE_LINE,
            targetId: $line->id,
            after: ['invoice_id' => $invoice->id, 'line_total' => (string) $line->line_total],
            request: $request,
        );

        return new BillingInvoiceLineResource($line);
    }

    public function update(UpdateBillingInvoiceLineRequest $request, SaasBillingInvoice $invoice, SaasBillingInvoiceLine $line): BillingInvoiceLineResource
    {
        if ((int) $line->invoice_id !== (int) $invoice->id) {
            throw new NotFoundHttpException('Line does not belong to this invoice.');
        }

        $line = $this->invoices->updateLine($invoice, $line, $request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_BILLING_INVOICE_LINE_UPDATED,
            targetType: AdminAuditLog::TARGET_SAAS_BILLING_INVOICE_LINE,
            targetId: $line->id,
            after: ['invoice_id' => $invoice->id, 'line_total' => (string) $line->line_total],
            request: $request,
        );

        return new BillingInvoiceLineResource($line);
    }
}
