<?php

namespace App\Services\Billing;

use App\Models\TenantBillingInvoice;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sprint 30 — read-only, redacted billing summaries (counts and aggregate
 * amounts only, never per-customer PII). Backs `billing:invoice-summary` and
 * `billing:collection-summary` and the admin collection summary endpoint.
 */
class BillingSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function invoiceSummary(?int $tenantId = null, ?string $periodKey = null): array
    {
        $query = TenantBillingInvoice::query();
        $this->applyScope($query, $tenantId, $periodKey);

        $rows = (clone $query)->get(['status', 'total_amount', 'currency']);

        $byStatus = [];
        foreach (config('billing_governance.invoice_statuses', []) as $status) {
            $byStatus[$status] = 0;
        }
        foreach ($rows as $row) {
            $byStatus[$row->status] = ($byStatus[$row->status] ?? 0) + 1;
        }

        return [
            'scope' => ['tenant_id' => $tenantId, 'period_key' => $periodKey],
            'total_invoices' => $rows->count(),
            'by_status' => $byStatus,
            'total_amount' => (int) $rows->sum('total_amount'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function collectionSummary(?int $tenantId = null, ?string $periodKey = null): array
    {
        $query = TenantBillingInvoice::query();
        $this->applyScope($query, $tenantId, $periodKey);

        $rows = (clone $query)->get();

        $byState = [];
        foreach (config('billing_governance.collection_states', []) as $state) {
            $byState[$state] = 0;
        }

        $totalBilled = 0;
        $totalCollected = 0;
        $totalOutstanding = 0;

        foreach ($rows as $invoice) {
            $byState[$invoice->collection_state] = ($byState[$invoice->collection_state] ?? 0) + 1;
            $totalBilled += (int) $invoice->total_amount;
            $totalCollected += $invoice->collectedAmount();
            $totalOutstanding += $invoice->outstandingAmount();
        }

        return [
            'scope' => ['tenant_id' => $tenantId, 'period_key' => $periodKey],
            'total_invoices' => $rows->count(),
            'by_collection_state' => $byState,
            'total_billed_amount' => $totalBilled,
            'total_collected_amount' => $totalCollected,
            'total_outstanding_amount' => $totalOutstanding,
        ];
    }

    private function applyScope(Builder $query, ?int $tenantId, ?string $periodKey): void
    {
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        if ($periodKey !== null) {
            $query->where('period_key', $periodKey);
        }
    }
}
