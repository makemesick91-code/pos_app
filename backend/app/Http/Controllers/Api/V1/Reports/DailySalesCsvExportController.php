<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportDateFilterRequest;
use App\Services\Reports\CsvReportExporter;
use App\Services\Reports\DailySalesReportService;
use App\Services\UsageEventLedger\ReportExportMeteringService;
use App\Support\TenantContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /api/v1/reports/daily-sales/export.csv — a simple, tenant-isolated CSV of
 * the daily sales summary (Sprint 9). It uses the same filters and the same
 * backend-authoritative figures as the JSON endpoint and never emits secrets or
 * raw gateway payloads.
 *
 * Sprint 27 — a successful export is metered as exactly one `report.exported`
 * usage event in the append-only ledger (UEL-R006, UEL-R007). The usage-limit
 * guard (tenant.usage.limit:reports.exports.monthly) has already run before this
 * controller, so an over-quota export is rejected with 429 USAGE_LIMIT_EXCEEDED
 * and never reaches this recording; a failure while building the summary throws
 * before recording, so a failed export never counts (UEL-R008).
 */
class DailySalesCsvExportController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DailySalesReportService $service,
        private readonly CsvReportExporter $exporter,
        private readonly ReportExportMeteringService $metering,
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

        // The export succeeded (summary computed, CSV built) — meter it exactly
        // once. Idempotent on retry; a suspended/unentitled/over-quota request was
        // already blocked upstream and never reaches this point (UEL-R007/R008).
        $tenant = $this->context->tenant();
        if ($tenant !== null) {
            $this->metering->recordExport(
                tenant: $tenant,
                actor: $this->context->user(),
                reportType: 'daily-sales',
                format: 'csv',
                filters: [
                    'store_id' => $request->input('store_id'),
                    'date_from' => optional($request->dateFrom())->toDateString(),
                    'date_to' => optional($request->dateTo())->toDateString(),
                    'cashier_id' => $request->input('cashier_id'),
                ],
                request: $request,
            );
        }

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
