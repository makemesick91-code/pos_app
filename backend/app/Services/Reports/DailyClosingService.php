<?php

namespace App\Services\Reports;

use App\Models\DailyClosing;
use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Creates the daily closing snapshot (Sprint 9). Every total is computed here by
 * the report services — the client never supplies a total. Exactly one closing
 * may exist per (tenant_id, store_id, business_date): a duplicate close request
 * replays the existing row (flagged duplicateReplay) instead of inserting a
 * second one, and a lost unique-index race is recovered the same way. There is
 * no reopen workflow in Sprint 9.
 */
class DailyClosingService
{
    public function __construct(
        private readonly DailySalesReportService $dailySales,
        private readonly PaymentSummaryReportService $paymentSummary,
        private readonly InventoryMovementSummaryService $inventory,
    ) {}

    public function close(
        int $tenantId,
        int $storeId,
        string $businessDate,
        int $closedBy,
        ?string $notes = null,
    ): DailyClosing {
        $date = Carbon::parse($businessDate)->toDateString();

        $existing = $this->findExisting($tenantId, $storeId, $date);
        if ($existing !== null) {
            $existing->duplicateReplay = true;

            return $existing;
        }

        $sales = $this->dailySales->summary($tenantId, $storeId, $date, $date);
        $payments = $this->paymentSummary->summary($tenantId, $storeId, $date, $date);
        $inventory = $this->inventory->summary($tenantId, $storeId, $date, $date);

        $cashTotal = $this->paymentSummary->paidTotalForMethod($tenantId, Payment::METHOD_CASH, $storeId, $date, $date);
        $qrisTotal = $this->paymentSummary->paidTotalForMethod($tenantId, Payment::METHOD_QRIS, $storeId, $date, $date);
        $saleOutQty = $this->inventory->saleOutQty($tenantId, $storeId, $date, $date);

        $attributes = [
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'business_date' => $date,
            'closed_by' => $closedBy,
            'closed_at' => Carbon::now(),
            'status' => DailyClosing::STATUS_CLOSED,
            'sales_count' => $sales['sales_count'],
            'cancelled_sales_count' => $sales['cancelled_sales_count'],
            'cash_total' => $cashTotal,
            'qris_total' => $qrisTotal,
            'gross_total' => $sales['gross_total'],
            'discount_total' => $sales['discount_total'],
            'tax_total' => $sales['tax_total'],
            'grand_total' => $sales['grand_total'],
            'paid_total' => $sales['paid_total'],
            'change_total' => $sales['change_total'],
            'inventory_sale_out_qty' => $saleOutQty,
            'snapshot' => [
                'daily_sales' => $sales,
                'payment_summary' => $payments,
                'inventory_movements_summary' => $inventory,
            ],
            'notes' => $notes,
        ];

        try {
            return DailyClosing::create($attributes);
        } catch (QueryException $e) {
            // Lost a race with a concurrent close: the unique guard rejected the
            // insert because the closing already exists. Replay it instead.
            $existing = $this->findExisting($tenantId, $storeId, $date);
            if ($existing !== null) {
                $existing->duplicateReplay = true;

                return $existing;
            }

            throw $e;
        }
    }

    private function findExisting(int $tenantId, int $storeId, string $date): ?DailyClosing
    {
        return DailyClosing::query()
            ->forTenant($tenantId)
            ->where('store_id', $storeId)
            ->whereDate('business_date', $date)
            ->first();
    }
}
