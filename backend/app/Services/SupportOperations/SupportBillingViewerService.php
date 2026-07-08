<?php

namespace App\Services\SupportOperations;

use App\Models\TenantBillingInvoice;

/**
 * Sprint 35 — read-only invoice/collection viewer (SUP-R008/R015).
 *
 * Reads the Sprint 30 tenant_billing_invoices safely. NEVER mutates invoice or
 * collection state, never marks an invoice paid, never lifts a suspension. Output
 * is an aggregate-safe summary; no raw metadata is returned.
 */
class SupportBillingViewerService
{
    public function summary(int $tenantId, int $limit = 20): array
    {
        $invoices = TenantBillingInvoice::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 100)))
            ->get();

        $byStatus = [];
        $byCollection = [];
        $outstanding = 0;
        foreach ($invoices as $invoice) {
            $byStatus[$invoice->status] = ($byStatus[$invoice->status] ?? 0) + 1;
            $byCollection[$invoice->collection_state] = ($byCollection[$invoice->collection_state] ?? 0) + 1;
            if (in_array($invoice->collection_state, [
                TenantBillingInvoice::COLLECTION_PENDING,
                TenantBillingInvoice::COLLECTION_OVERDUE,
                TenantBillingInvoice::COLLECTION_FAILED,
            ], true)) {
                $outstanding += (int) $invoice->total_amount;
            }
        }

        return [
            'read_only' => true,
            'invoice_count' => $invoices->count(),
            'by_status' => $byStatus,
            'by_collection_state' => $byCollection,
            'outstanding_amount' => $outstanding,
            'currency' => optional($invoices->first())->currency,
            'latest' => $invoices->take(5)->map(fn (TenantBillingInvoice $i) => [
                'invoice_number' => $i->invoice_number,
                'period_key' => $i->period_key,
                'plan_key' => $i->plan_key,
                'status' => $i->status,
                'collection_state' => $i->collection_state,
                'total_amount' => (int) $i->total_amount,
                'due_at' => optional($i->due_at)->toIso8601String(),
                'issued_at' => optional($i->issued_at)->toIso8601String(),
            ])->all(),
        ];
    }
}
