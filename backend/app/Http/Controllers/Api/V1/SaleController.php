<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CancelSaleRequest;
use App\Http\Requests\Api\V1\IndexSaleRequest;
use App\Http\Requests\Api\V1\StoreSaleRequest;
use App\Http\Resources\Api\V1\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Tenant-isolated sales API. Every query is scoped to the authenticated tenant;
 * show/cancel/pay verify ownership and 404 otherwise so a tenant can never learn
 * that another tenant's sale exists.
 */
class SaleController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SaleService $sales,
    ) {}

    public function index(IndexSaleRequest $request): AnonymousResourceCollection
    {
        $query = Sale::query()
            ->forTenant($this->context->tenantId())
            ->with(['items', 'payments'])
            ->orderByDesc('sale_date')
            ->orderByDesc('id');

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->input('store_id'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('sale_date', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('sale_date', '<=', $request->date('date_to'));
        }

        $perPage = (int) $request->input('per_page', 20);

        return SaleResource::collection($query->paginate($perPage));
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $sale = $this->sales->createCashSale($this->context, $request->validated());

        return SaleResource::make($sale)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Sale $sale): SaleResource
    {
        $this->authorizeTenant($sale);

        return SaleResource::make($sale->load(['items', 'payments']));
    }

    public function cancel(CancelSaleRequest $request, Sale $sale): SaleResource
    {
        $this->authorizeTenant($sale);

        return SaleResource::make($this->sales->cancel($sale, $this->context->user()));
    }

    private function authorizeTenant(Sale $sale): void
    {
        abort_unless(
            (int) $sale->tenant_id === (int) $this->context->tenantId(),
            404
        );
    }
}
