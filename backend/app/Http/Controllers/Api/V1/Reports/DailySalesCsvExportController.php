<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportDateFilterRequest;
use App\Services\Reports\CsvReportExporter;
use App\Services\Reports\DailySalesReportService;
use App\Support\TenantContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /api/v1/reports/daily-sales/export.csv — a simple, tenant-isolated CSV of
 * the daily sales summary (Sprint 9). It uses the same filters and the same
 * backend-authoritative figures as the JSON endpoint and never emits secrets or
 * raw gateway payloads.
 */
class DailySalesCsvExportController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DailySalesReportService $service,
        private readonly CsvReportExporter $exporter,
    ) {}

    public function index(ReportDateFilterRequest $request): StreamedResponse
    {
        $tenantId = (int) $this->context->tenantId();

        $summary = $this->service->summary(
            tenantId: $tenantId,
            storeId: $request->filled('store_id') ? (int) $request->input('store_id') : null,
            dateFrom: $request->dateFrom(),
            dateTo: $request->dateTo(),
            cashierId: $request->filled('cashier_id') ? (int) $request->input('cashier_id') : null,
        );

        $csv = $this->exporter->dailySales($summary);
        $filename = 'daily-sales-'.$summary['business_date'].'.csv';

        return response()->streamDownload(
            function () use ($csv) {
                echo $csv;
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ],
        );
    }
}
