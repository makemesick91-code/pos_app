<?php

namespace App\Services\Reports;

/**
 * Builds simple, tenant-isolated CSV exports for the Sprint 9 reports. The
 * exporter only ever receives already-summarized, backend-authoritative data —
 * it never touches raw gateway payloads, secrets, or another tenant's rows. Only
 * a flat header row plus value rows are emitted (no PDF/Excel/BI output).
 */
class CsvReportExporter
{
    /** Column order for the daily sales CSV. */
    public const DAILY_SALES_COLUMNS = [
        'business_date',
        'store_id',
        'sales_count',
        'cancelled_sales_count',
        'gross_total',
        'discount_total',
        'tax_total',
        'grand_total',
        'paid_total',
        'change_total',
    ];

    /**
     * Render a single daily sales summary as CSV (header + one data row).
     *
     * @param  array<string, mixed>  $summary
     */
    public function dailySales(array $summary): string
    {
        $row = [];
        foreach (self::DAILY_SALES_COLUMNS as $column) {
            $row[] = $summary[$column] ?? '';
        }

        return $this->line(self::DAILY_SALES_COLUMNS).$this->line($row);
    }

    /**
     * @param  array<int|string, mixed>  $fields
     */
    private function line(array $fields): string
    {
        $escaped = array_map(function ($field) {
            $value = (string) $field;
            if (preg_match('/[",\n\r]/', $value) === 1) {
                $value = '"'.str_replace('"', '""', $value).'"';
            }

            return $value;
        }, $fields);

        return implode(',', $escaped)."\n";
    }
}
