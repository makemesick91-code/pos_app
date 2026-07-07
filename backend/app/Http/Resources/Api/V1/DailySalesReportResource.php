<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the backend-computed daily sales summary array (Sprint 9). The resource
 * never recomputes anything — it simply presents the authoritative figures the
 * DailySalesReportService produced.
 *
 * @property array<string, mixed> $resource
 */
class DailySalesReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'business_date' => $this->resource['business_date'],
            'store_id' => $this->resource['store_id'],
            'sales_count' => $this->resource['sales_count'],
            'cancelled_sales_count' => $this->resource['cancelled_sales_count'],
            'gross_total' => $this->resource['gross_total'],
            'discount_total' => $this->resource['discount_total'],
            'tax_total' => $this->resource['tax_total'],
            'grand_total' => $this->resource['grand_total'],
            'paid_total' => $this->resource['paid_total'],
            'change_total' => $this->resource['change_total'],
            'average_sale' => $this->resource['average_sale'],
            'cash_sales_count' => $this->resource['cash_sales_count'],
            'qris_sales_count' => $this->resource['qris_sales_count'],
        ];
    }
}
