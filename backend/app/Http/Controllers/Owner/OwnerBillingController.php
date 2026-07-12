<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Concerns\DeliversInvoiceDocument;
use App\Models\AdminAuditLog;
use App\Services\Admin\AdminAuditLogger;
use App\Services\BillingConsole\BillingConsoleReadService;
use App\Services\OwnerConsole\OwnerConsoleReadService;
use App\Services\OwnerConsole\OwnerContextResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * UIX-5 — Tenant Owner Billing Center (`/owner/billing/*`).
 *
 * Read-only, tenant-scoped to the authenticated owner's OWN tenant only. The
 * tenant is derived server-side from {@see OwnerContext} — never from a route
 * parameter, query string, header, or cookie (UIX4-R004/R005, UIX5-R003). Every
 * invoice read/download is resolved through
 * {@see BillingConsoleReadService::findInvoice()} scoped to `tenantId()`, so a
 * foreign or unknown invoice id returns 404 (UIX5-R006). Financial values come
 * from canonical services; nothing is recomputed here (UIX5-R002/R010).
 *
 * Billing pages are intentionally reachable even for a suspended/archived tenant
 * (like `/owner/subscription`) so an owner can always see — and act on — what
 * they owe. Business-operations pages remain restricted by the base controller.
 */
class OwnerBillingController extends OwnerController
{
    use DeliversInvoiceDocument;

    public function __construct(
        OwnerContextResolver $resolver,
        OwnerConsoleReadService $read,
        private readonly BillingConsoleReadService $billing,
        private readonly AdminAuditLogger $auditLogger,
    ) {
        parent::__construct($resolver, $read);
    }

    public function index(): View
    {
        $context = $this->context();

        return view('owner.billing.overview', [
            'context' => $context,
            'data' => $this->billing->ownerOverview($context),
        ]);
    }

    public function invoices(Request $request): View
    {
        $context = $this->context();
        $filters = $this->invoiceFilters($request);
        $paginator = $this->billing->paginateInvoices($context->tenantId(), $filters);

        return view('owner.billing.invoices', [
            'context' => $context,
            'paginator' => $paginator,
            'rows' => array_map(
                fn ($invoice) => $this->billing->presentInvoice($invoice),
                $paginator->items(),
            ),
            'filters' => $filters,
            'statusOptions' => $this->billing->invoiceStatuses(),
            'collectionOptions' => $this->billing->collectionStates(),
        ]);
    }

    public function showInvoice(Request $request, int $invoice): View
    {
        $context = $this->context();
        $model = $this->billing->findInvoice($context->tenantId(), $invoice);
        abort_if($model === null, 404);

        $this->auditLogger->log(
            actor: $context->user,
            action: AdminAuditLog::ACTION_OWNER_INVOICE_VIEWED,
            targetType: AdminAuditLog::TARGET_BILLING_INVOICE,
            targetId: (int) $model->id,
            tenantId: $context->tenantId(),
            metadata: ['channel' => 'owner_console', 'invoice_number' => $model->invoice_number],
            request: $request,
        );

        return view('owner.billing.invoice', [
            'context' => $context,
            'data' => $this->billing->invoiceDetail($model),
        ]);
    }

    public function downloadInvoice(Request $request, int $invoice): Response
    {
        $context = $this->context();
        $model = $this->billing->findInvoice($context->tenantId(), $invoice);
        abort_if($model === null, 404);

        $this->auditLogger->log(
            actor: $context->user,
            action: AdminAuditLog::ACTION_OWNER_INVOICE_DOWNLOADED,
            targetType: AdminAuditLog::TARGET_BILLING_INVOICE,
            targetId: (int) $model->id,
            tenantId: $context->tenantId(),
            metadata: ['channel' => 'owner_console', 'invoice_number' => $model->invoice_number],
            request: $request,
        );

        return $this->invoiceDocumentResponse($this->billing, $model);
    }
}
