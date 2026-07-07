<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStorePrice;

/**
 * Resolves the effective unit price for a product at checkout time. The client
 * never dictates unit_price — it is always resolved here from tenant-owned data:
 *
 *   1. If an active ProductStorePrice exists for the store context, its
 *      selling_price override wins.
 *   2. Otherwise the product's own selling_price is used.
 *
 * The returned value is snapshotted into sale_items so later catalog/price edits
 * never rewrite historical transactions.
 */
class ProductPriceResolver
{
    public function resolve(Product $product, ?int $storeId): string
    {
        if ($storeId !== null) {
            $override = ProductStorePrice::query()
                ->where('tenant_id', $product->tenant_id)
                ->where('store_id', $storeId)
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->first();

            if ($override !== null) {
                return (string) $override->selling_price;
            }
        }

        return (string) $product->selling_price;
    }
}
