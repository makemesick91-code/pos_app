<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCashPaymentRequest;
use App\Http\Resources\Api\V1\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use App\Support\TenantContext;

/**
 * Finalizes an existing UNPAID sale with CASH. Provided as a foundation endpoint
 * per the document; the primary Sprint 4 Android flow creates a paid sale
 * directly via SaleController::store. Cross-tenant sales 404.
 */
class SaleCashPaymentController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SaleService $sales,
    ) {}

    public function store(StoreCashPaymentRequest $request, Sale $sale): SaleResource
    {
        abort_unless(
            (int) $sale->tenant_id === (int) $this->context->tenantId(),
            404
        );

        $sale = $this->sales->payCash($sale, $request->input('paid_amount'));

        return SaleResource::make($sale);
    }
}
