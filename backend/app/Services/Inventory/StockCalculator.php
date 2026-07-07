<?php

namespace App\Services\Inventory;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Derives current stock as the signed sum of inventory_movements. This is the
 * ONLY authoritative stock figure — there is no mutable stock column. Every
 * query is tenant- and store-scoped so one tenant can never read another's
 * stock. See Sprint 8 evidence.
 */
class StockCalculator
{
    private const SCALE = 2;

    /**
     * Current stock for a single product in a store, as a decimal string.
     * Returns "0.00" when the product has no movements.
     */
    public function currentStock(int $tenantId, int $storeId, int $productId): string
    {
        $sum = InventoryMovement::query()
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->sum('signed_qty');

        return $this->format($sum);
    }

    /**
     * Current stock for many products at once, keyed by product_id. Products
     * without movements are absent from the map (callers default them to 0).
     *
     * @param  array<int, int>  $productIds
     * @return array<int, string>
     */
    public function currentStockForProducts(int $tenantId, int $storeId, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return InventoryMovement::query()
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(signed_qty) as total')
            ->pluck('total', 'product_id')
            ->map(fn ($total) => $this->format($total))
            ->all();
    }

    /**
     * A lightweight current-stock listing: tenant's products in a store context,
     * each annotated with its derived stock. Not a report — bounded by $limit.
     *
     * @param  array<string, mixed>  $filters  q, category_id, active, limit
     * @return Collection<int, array<string, mixed>>
     */
    public function currentStockList(int $tenantId, int $storeId, array $filters = []): Collection
    {
        $limit = (int) ($filters['limit'] ?? 50);
        $limit = max(1, min($limit, 200));

        $query = Product::query()
            ->where('tenant_id', $tenantId)
            ->forStoreContext($storeId);

        if (! empty($filters['q'])) {
            $query->search($filters['q']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (array_key_exists('active', $filters) && $filters['active'] !== null) {
            $query->where('is_active', (bool) $filters['active']);
        }

        $products = $query->orderBy('name')->limit($limit)->get();

        $stockMap = $this->currentStockForProducts(
            $tenantId,
            $storeId,
            $products->pluck('id')->all(),
        );

        return $products->map(fn (Product $product) => [
            'product_id' => (int) $product->id,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'unit' => $product->unit,
            'is_stock_tracked' => (bool) $product->is_stock_tracked,
            'current_stock' => $stockMap[$product->id] ?? '0.00',
        ]);
    }

    private function format(mixed $value): string
    {
        return bcadd((string) ($value ?? '0'), '0', self::SCALE);
    }
}
