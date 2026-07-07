<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportDateFilterRequest;
use App\Http\Resources\Api\V1\DailySalesReportResource;
use App\Services\Reports\DailySalesReportService;
use App\Support\TenantContext;

/**
 * GET /api/v1/reports/daily-sales — the tenant-isolated daily sales summary.
 * Only PAID sales count as revenue; cancelled sales are reported separately.
 * Every figure is computed by the backend service (Sprint 9).
 */
class DailySalesReportController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DailySalesReportService $service,
    ) {}

    public function index(ReportDateFilterRequest $request): DailySalesReportResource
    {
        $tenantId = (int) $this->context->tenantId();

        $summary = $this->service->summary(
            tenantId: $tenantId,
            storeId: $request->filled('store_id') ? (int) $request->input('store_id') : null,
            dateFrom: $request->dateFrom(),
            dateTo: $request->dateTo(),
            cashierId: $request->filled('cashier_id') ? (int) $request->input('cashier_id') : null,
        );

        return DailySalesReportResource::make($summary)->additional([
            'meta' => [
                'tenant_id' => $tenantId,
                'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
            ],
        ]);
    }
}
