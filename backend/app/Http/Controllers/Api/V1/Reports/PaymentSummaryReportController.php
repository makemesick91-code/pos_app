<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportDateFilterRequest;
use App\Http\Resources\Api\V1\PaymentSummaryResource;
use App\Services\Reports\PaymentSummaryReportService;
use App\Support\TenantContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/reports/payment-summary — payments grouped by (method, status)
 * for the tenant/store/date window. Only PAID rows represent realized revenue
 * (Sprint 9). Tenant-isolated; never exposes raw gateway payloads.
 */
class PaymentSummaryReportController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PaymentSummaryReportService $service,
    ) {}

    public function index(ReportDateFilterRequest $request): AnonymousResourceCollection
    {
        $tenantId = (int) $this->context->tenantId();

        $rows = $this->service->summary(
            tenantId: $tenantId,
            storeId: $request->filled('store_id') ? (int) $request->input('store_id') : null,
            dateFrom: $request->dateFrom(),
            dateTo: $request->dateTo(),
        );

        return PaymentSummaryResource::collection($rows)->additional([
            'meta' => [
                'tenant_id' => $tenantId,
                'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
            ],
        ]);
    }
}
