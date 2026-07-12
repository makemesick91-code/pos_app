<?php

namespace App\Http\Controllers\Concerns;

use App\Models\TenantBillingInvoice;
use App\Services\BillingConsole\BillingConsoleReadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * UIX-5 — shared, authenticated invoice-document delivery for the Owner and
 * Admin billing consoles.
 *
 * The invoice is delivered as print-ready HTML rendered server-side from
 * canonical data (there is no rendered-PDF generator in this codebase, so a
 * fragile large PDF dependency is deliberately NOT added — UIX5 §19). Delivery
 * is always authenticated and authorized by the caller BEFORE this runs: the
 * caller resolves the invoice within its own scope and 404s a foreign invoice.
 * There is NO public/direct-storage URL and NO arbitrary file/path parameter, so
 * path traversal and object enumeration are structurally impossible (UIX5-R007/
 * R018). The response is private and non-cacheable (UIX5-R014/R020-adjacent).
 */
trait DeliversInvoiceDocument
{
    /**
     * Parse the whitelisted invoice-list filters from the request. Unknown sort/
     * status/collection values are dropped by the read service, so this only
     * normalises shape (UIX5-R021/R022).
     *
     * @return array<string, mixed>
     */
    protected function invoiceFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'collection' => (string) $request->query('collection', ''),
            'period' => (string) $request->query('period', ''),
            'sort' => (string) $request->query('sort', 'issued_at'),
            'direction' => (string) $request->query('direction', 'desc'),
            'per_page' => (int) $request->query('per_page', 20),
        ];
    }

    /**
     * Build the authenticated invoice-document HTTP response. The filename is
     * derived from the (sanitised) canonical invoice number, never from request
     * input.
     */
    protected function invoiceDocumentResponse(BillingConsoleReadService $billing, TenantBillingInvoice $invoice): Response
    {
        $html = View::make('billing.invoice-document', $billing->invoiceDocument($invoice))->render();

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $invoice->invoice_number);
        $safeName = trim((string) $safeName, '-._');
        if ($safeName === '') {
            $safeName = 'invoice-'.(int) $invoice->id;
        }

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$safeName.'.html"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
        ]);
    }
}
