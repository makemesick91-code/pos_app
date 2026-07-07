<?php

namespace App\Services\Reports;

use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Support\Carbon;

/**
 * Computes the authoritative daily sales summary from the sales table
 * (Sprint 9). Only PAID sales count as revenue; CANCELLED sales are counted
 * separately and never as revenue; pending/failed/expired QRIS sales are not
 * PAID and therefore excluded from revenue. Offline cash sales are only present
 * here after they have been synced to the backend, so they naturally count only
 * once synced. Every figure is backend-derived — the client never supplies a
 * total.
 */
class DailySalesReportService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(
        int $tenantId,
        ?int $storeId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $cashierId = null,
    ): array {
        $from = $dateFrom ?? Carbon::now()->toDateString();
        $to = $dateTo ?? $from;

        $base = Sale::query()
            ->forTenant($tenantId)
            ->whereDate('sale_date', '>=', $from)
            ->whereDate('sale_date', '<=', $to);

        if ($storeId !== null) {
            $base->where('store_id', $storeId);
        }

        if ($cashierId !== null) {
            $base->where('cashier_id', $cashierId);
        }

        $paid = (clone $base)->where('payment_status', Sale::PAYMENT_STATUS_PAID);

        $agg = (clone $paid)
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as gross_total')
            ->selectRaw('COALESCE(SUM(discount_total), 0) as discount_total')
            ->selectRaw('COALESCE(SUM(tax_total), 0) as tax_total')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as grand_total')
            ->selectRaw('COALESCE(SUM(paid_total), 0) as paid_total')
            ->selectRaw('COALESCE(SUM(change_total), 0) as change_total')
            ->first();

        $salesCount = (int) $agg->sales_count;
        $cancelledCount = (clone $base)
            ->where('payment_status', Sale::PAYMENT_STATUS_CANCELLED)
            ->count();

        $grandTotal = (float) $agg->grand_total;
        $averageSale = $salesCount > 0 ? $grandTotal / $salesCount : 0.0;

        $cashSalesCount = (clone $paid)
            ->whereHas('payments', fn ($q) => $q
                ->where('status', Payment::STATUS_PAID)
                ->where('method', Payment::METHOD_CASH))
            ->count();

        $qrisSalesCount = (clone $paid)
            ->whereHas('payments', fn ($q) => $q
                ->where('status', Payment::STATUS_PAID)
                ->where('method', Payment::METHOD_QRIS))
            ->count();

        return [
            'business_date' => $from,
            'store_id' => $storeId,
            'sales_count' => $salesCount,
            'cancelled_sales_count' => $cancelledCount,
            'gross_total' => $this->money($agg->gross_total),
            'discount_total' => $this->money($agg->discount_total),
            'tax_total' => $this->money($agg->tax_total),
            'grand_total' => $this->money($agg->grand_total),
            'paid_total' => $this->money($agg->paid_total),
            'change_total' => $this->money($agg->change_total),
            'average_sale' => $this->money($averageSale),
            'cash_sales_count' => $cashSalesCount,
            'qris_sales_count' => $qrisSalesCount,
        ];
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
