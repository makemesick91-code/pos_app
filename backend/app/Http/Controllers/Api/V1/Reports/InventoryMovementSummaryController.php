<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportDateFilterRequest;
use App\Http\Resources\Api\V1\InventoryMovementSummaryResource;
use App\Services\Reports\InventoryMovementSummaryService;
use App\Support\TenantContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/reports/inventory-movements-summary — inventory movements grouped
 * by movement_type for the tenant/store/date window, derived from the
 * inventory_movements ledger (Sprint 9). Tenant-isolated; no valuation.
 */
class InventoryMovementSummaryController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly InventoryMovementSummaryService $service,
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

        return InventoryMovementSummaryResource::collection($rows)->additional([
            'meta' => [
                'tenant_id' => $tenantId,
                'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
            ],
        ]);
    }
}
