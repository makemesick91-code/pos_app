<?php

namespace App\Services\Reports;

use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * Summarizes payments grouped by (method, status) for a tenant/store/date range
 * (Sprint 9). Only rows with status PAID represent realized revenue; PENDING /
 * FAILED / EXPIRED / CANCELLED rows are reported separately and never mixed into
 * a paid total. Every query is tenant-isolated and filtered by the owning sale's
 * business date so it aligns with the daily sales summary.
 */
class PaymentSummaryReportService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function summary(
        int $tenantId,
        ?int $storeId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $from = $dateFrom ?? Carbon::now()->toDateString();
        $to = $dateTo ?? $from;

        $query = Payment::query()
            ->forTenant($tenantId)
            ->whereHas('sale', fn ($s) => $s
                ->whereDate('sale_date', '>=', $from)
                ->whereDate('sale_date', '<=', $to));

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        $rows = $query
            ->selectRaw('method, status, COUNT(*) as count, COALESCE(SUM(amount), 0) as amount_total')
            ->groupBy('method', 'status')
            ->orderBy('method')
            ->orderBy('status')
            ->get();

        return $rows->map(fn ($row) => [
            'method' => $row->method,
            'status' => $row->status,
            'count' => (int) $row->count,
            'amount_total' => number_format((float) $row->amount_total, 2, '.', ''),
        ])->all();
    }

    /**
     * Total PAID amount for a single method within the range. Used by the daily
     * closing snapshot so cash/qris totals come from the same authoritative
     * summary the report endpoint exposes.
     */
    public function paidTotalForMethod(
        int $tenantId,
        string $method,
        ?int $storeId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): string {
        $total = 0.0;
        foreach ($this->summary($tenantId, $storeId, $dateFrom, $dateTo) as $row) {
            if ($row['method'] === $method && $row['status'] === Payment::STATUS_PAID) {
                $total += (float) $row['amount_total'];
            }
        }

        return number_format($total, 2, '.', '');
    }
}
