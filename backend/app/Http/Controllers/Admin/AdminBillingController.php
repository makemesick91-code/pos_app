<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\DeliversInvoiceDocument;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Services\Admin\AdminAuditLogger;
use App\Services\BillingConsole\BillingConsoleReadService;
use App\Services\TenantLifecycle\TenantLifecycleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * UIX-5 — Platform Admin Billing Operations (`/admin/billing/*`).
 *
 * Read-only platform-level billing visibility for platform admins. Reachable
 * only behind the `platform.admin.web` route gate (enforced by the route group,
 * never inline). Platform admins read ACROSS tenants BY DESIGN, but only through
 * the canonical summary services and scoped queries — this is a platform
 * authorization, and it NEVER grants tenant-owner membership (UIX5-R004). There
 * are NO mutation routes; the only writes are audit records (UIX5-R015/R016).
 *
 * All financial figures come from {@see BillingConsoleReadService}, which reads
 * canonical columns/methods and never recomputes invoice/paid/settlement state.
 */
class AdminBillingController extends Controller
{
    use DeliversInvoiceDocument;

    public function __construct(
        private readonly BillingConsoleReadService $billing,
        private readonly TenantLifecycleService $lifecycle,
        private readonly AdminAuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $this->auditLogger->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_ADMIN_BILLING_VIEWED,
            targetType: AdminAuditLog::TARGET_BILLING_INVOICE,
            metadata: ['channel' => 'web_console', 'scope' => 'platform'],
            request: $request,
        );

        return view('admin.billing.overview', [
            'data' => $this->billing->adminOverview(),
        ]);
    }

    public function invoices(Request $request): View
    {
        $filters = $this->invoiceFilters($request);
        $paginator = $this->billing->paginateInvoices(null, $filters);

        return view('admin.billing.invoices', [
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
        $model = $this->billing->findInvoice(null, $invoice);
        abort_if($model === null, 404);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_ADMIN_INVOICE_VIEWED,
            targetType: AdminAuditLog::TARGET_BILLING_INVOICE,
            targetId: (int) $model->id,
            tenantId: (int) $model->tenant_id,
            metadata: ['channel' => 'web_console', 'invoice_number' => $model->invoice_number],
            request: $request,
        );

        return view('admin.billing.invoice', [
            'data' => $this->billing->invoiceDetail($model),
        ]);
    }

    public function tenantBilling(Request $request, Tenant $tenant): View
    {
        $this->auditLogger->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_ADMIN_BILLING_VIEWED,
            targetType: AdminAuditLog::TARGET_BILLING_INVOICE,
            tenantId: (int) $tenant->id,
            metadata: ['channel' => 'web_console', 'scope' => 'tenant'],
            request: $request,
        );

        return view('admin.tenants.billing', [
            'tenant' => $tenant,
            'lifecycle' => $this->lifecycle->resolve($tenant)->toArray(),
            'data' => $this->billing->adminTenantBilling((int) $tenant->id),
        ]);
    }

    public function downloadInvoice(Request $request, int $invoice): Response
    {
        $model = $this->billing->findInvoice(null, $invoice);
        abort_if($model === null, 404);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_ADMIN_INVOICE_DOWNLOADED,
            targetType: AdminAuditLog::TARGET_BILLING_INVOICE,
            targetId: (int) $model->id,
            tenantId: (int) $model->tenant_id,
            metadata: ['channel' => 'web_console', 'invoice_number' => $model->invoice_number],
            request: $request,
        );

        return $this->invoiceDocumentResponse($this->billing, $model);
    }
}
