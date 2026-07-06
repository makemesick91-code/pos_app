<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductSyncRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStorePrice;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Lightweight Android sync endpoints for the product catalog.
 *
 * Output is scoped to the authenticated tenant and the selected store:
 * global (store_id null) rows plus the selected store's rows. Store price
 * overrides are merged into effective_selling_price. Incremental sync is
 * supported via updated_since. Never leaks another tenant's data.
 */
class ProductSyncController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function products(ProductSyncRequest $request): JsonResponse
    {
        $tenantId = $this->context->tenantId();
        $storeId = $this->resolveStoreId($request);
        $updatedSince = $request->input('updated_since');

        $products = Product::query()
            ->forTenant($tenantId)
            ->forStoreContext($storeId)
            ->updatedSince($updatedSince)
            ->orderBy('id')
            ->get();

        // Active price overrides for the selected store, keyed by product id.
        $overrides = collect();
        if ($storeId !== null) {
            $overrides = ProductStorePrice::query()
                ->forTenant($tenantId)
                ->where('store_id', $storeId)
                ->active()
                ->get()
                ->keyBy('product_id');
        }

        $data = $products->map(function (Product $product) use ($overrides) {
            $override = $overrides->get($product->id);
            $effective = $override !== null ? $override->selling_price : $product->selling_price;

            return [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'unit' => $product->unit,
                'selling_price' => $product->selling_price,
                'effective_selling_price' => $effective,
                'is_stock_tracked' => $product->is_stock_tracked,
                'is_active' => $product->is_active,
                'updated_at' => $product->updated_at,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => $this->meta($tenantId, $storeId, $updatedSince),
        ]);
    }

    public function categories(ProductSyncRequest $request): JsonResponse
    {
        $tenantId = $this->context->tenantId();
        $storeId = $this->resolveStoreId($request);
        $updatedSince = $request->input('updated_since');

        $categories = ProductCategory::query()
            ->forTenant($tenantId)
            ->forStoreContext($storeId)
            ->updatedSince($updatedSince)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $data = $categories->map(fn (ProductCategory $category) => [
            'id' => $category->id,
            'name' => $category->name,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
            'updated_at' => $category->updated_at,
        ]);

        return response()->json([
            'data' => $data,
            'meta' => $this->meta($tenantId, $storeId, $updatedSince),
        ]);
    }

    /**
     * Selected store: explicit validated store_id wins, otherwise the tenant
     * context store (user's own store or a validated X-Store-ID).
     */
    private function resolveStoreId(ProductSyncRequest $request): ?int
    {
        if ($request->filled('store_id')) {
            return (int) $request->input('store_id');
        }

        return $this->context->storeId();
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(?int $tenantId, ?int $storeId, ?string $updatedSince): array
    {
        return [
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'updated_since' => $updatedSince,
            'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
        ];
    }
}
