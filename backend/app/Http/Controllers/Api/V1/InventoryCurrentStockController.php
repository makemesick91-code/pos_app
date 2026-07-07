<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexCurrentStockRequest;
use App\Http\Resources\Api\V1\CurrentStockResource;
use App\Models\Product;
use App\Services\Inventory\StockCalculator;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Read-only, tenant-isolated current-stock endpoints. Stock is always derived
 * from the inventory ledger (StockCalculator) — there is no mutable stock
 * column. Lightweight by design: bounded lists, no reporting. See Sprint 8.
 */
class InventoryCurrentStockController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly StockCalculator $stock,
    ) {}

    public function index(IndexCurrentStockRequest $request): AnonymousResourceCollection
    {
        $tenantId = (int) $this->context->tenantId();
        $storeId = $this->resolveStoreId($request->input('store_id'));

        $rows = $this->stock->currentStockList($tenantId, $storeId, [
            'q' => $request->input('q'),
            'category_id' => $request->input('category_id'),
            'active' => $request->has('active') ? $request->boolean('active') : null,
            'limit' => $request->input('limit', 50),
        ]);

        return CurrentStockResource::collection($rows)->additional([
            'meta' => [
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
            ],
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $tenantId = (int) $this->context->tenantId();

        // A tenant must never learn another tenant's product exists.
        abort_unless((int) $product->tenant_id === $tenantId, 404);

        $storeId = $this->resolveStoreId($this->context->storeId());

        $currentStock = $this->stock->currentStock($tenantId, $storeId, (int) $product->id);

        return response()->json([
            'data' => [
                'product_id' => (int) $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => $product->unit,
                'is_stock_tracked' => (bool) $product->is_stock_tracked,
                'current_stock' => $currentStock,
            ],
            'meta' => [
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
            ],
        ]);
    }

    private function resolveStoreId(mixed $requested): int
    {
        $storeId = $requested !== null ? (int) $requested : $this->context->storeId();

        if ($storeId === null) {
            throw ValidationException::withMessages([
                'store_id' => 'A store context is required. Assign the user to a store or send a valid store_id.',
            ]);
        }

        return (int) $storeId;
    }
}
