<?php

namespace App\Services\Inventory;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * Writes to the inventory ledger. Every entry is tenant/store/product owned and
 * the stock-impacting `signed_qty` is ALWAYS derived here from the movement
 * type — a client can never set it. `qty` is validated positive.
 *
 * SALE_OUT is created only for stock-tracked products and references the
 * originating sale item, which (together with the DB unique guard) makes an
 * idempotent offline sale replay safe: the second attempt cannot duplicate the
 * decrement. See Sprint 8 evidence.
 */
class InventoryMovementService
{
    private const SCALE = 2;

    /**
     * Create an OPENING / ADJUSTMENT_IN / ADJUSTMENT_OUT movement from the
     * adjustment endpoint. SALE_OUT is intentionally NOT reachable here.
     */
    public function createAdjustment(
        int $tenantId,
        int $storeId,
        int $productId,
        string $movementType,
        string $qty,
        ?string $notes = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        if (! in_array($movementType, InventoryMovement::ADJUSTMENT_TYPES, true)) {
            throw ValidationException::withMessages([
                'movement_type' => 'This movement type cannot be created through the adjustment endpoint.',
            ]);
        }

        $this->assertStoreOwnedByTenant($tenantId, $storeId);
        $this->assertProductOwnedByTenant($tenantId, $productId);

        return $this->create(
            tenantId: $tenantId,
            storeId: $storeId,
            productId: $productId,
            movementType: $movementType,
            qty: $qty,
            source: $movementType === InventoryMovement::TYPE_OPENING
                ? InventoryMovement::SOURCE_OPENING
                : InventoryMovement::SOURCE_ADJUSTMENT,
            notes: $notes,
            createdBy: $createdBy,
        );
    }

    public function createOpeningMovement(
        int $tenantId,
        int $storeId,
        int $productId,
        string $qty,
        ?string $notes = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        return $this->createAdjustment(
            $tenantId, $storeId, $productId, InventoryMovement::TYPE_OPENING, $qty, $notes, $createdBy,
        );
    }

    public function createAdjustmentIn(
        int $tenantId,
        int $storeId,
        int $productId,
        string $qty,
        ?string $notes = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        return $this->createAdjustment(
            $tenantId, $storeId, $productId, InventoryMovement::TYPE_ADJUSTMENT_IN, $qty, $notes, $createdBy,
        );
    }

    public function createAdjustmentOut(
        int $tenantId,
        int $storeId,
        int $productId,
        string $qty,
        ?string $notes = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        return $this->createAdjustment(
            $tenantId, $storeId, $productId, InventoryMovement::TYPE_ADJUSTMENT_OUT, $qty, $notes, $createdBy,
        );
    }

    /**
     * Create a SALE_OUT ledger entry for a single sale item. Skips products that
     * are not stock-tracked. Idempotent: a duplicate (tenant, store, product,
     * SALE_OUT, sale_item, id) is silently ignored so an offline replay never
     * double-decrements.
     */
    public function createSaleOutForSaleItem(SaleItem $item, Product $product): ?InventoryMovement
    {
        if (! $product->is_stock_tracked) {
            return null;
        }

        $existing = InventoryMovement::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('store_id', $item->store_id)
            ->where('product_id', $item->product_id)
            ->where('movement_type', InventoryMovement::TYPE_SALE_OUT)
            ->where('reference_type', InventoryMovement::REFERENCE_SALE_ITEM)
            ->where('reference_id', $item->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return $this->create(
                tenantId: (int) $item->tenant_id,
                storeId: (int) $item->store_id,
                productId: (int) $item->product_id,
                movementType: InventoryMovement::TYPE_SALE_OUT,
                qty: (string) $item->qty,
                source: InventoryMovement::SOURCE_SALE,
                referenceType: InventoryMovement::REFERENCE_SALE_ITEM,
                referenceId: (int) $item->id,
            );
        } catch (QueryException $e) {
            // Lost a race with a concurrent replay: the unique guard fired, so a
            // SALE_OUT for this line already exists. Return it instead of failing.
            $existing = InventoryMovement::query()
                ->where('tenant_id', $item->tenant_id)
                ->where('store_id', $item->store_id)
                ->where('product_id', $item->product_id)
                ->where('movement_type', InventoryMovement::TYPE_SALE_OUT)
                ->where('reference_type', InventoryMovement::REFERENCE_SALE_ITEM)
                ->where('reference_id', $item->id)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            throw $e;
        }
    }

    /**
     * Create SALE_OUT entries for every stock-tracked line of a sale.
     */
    public function createSaleOutForSale(Sale $sale): void
    {
        $sale->loadMissing('items');

        foreach ($sale->items as $item) {
            $product = Product::query()
                ->where('tenant_id', $sale->tenant_id)
                ->whereKey($item->product_id)
                ->first();

            if ($product === null) {
                continue;
            }

            $this->createSaleOutForSaleItem($item, $product);
        }
    }

    /**
     * The low-level insert. `signed_qty` is computed here from the movement type
     * — the single place the stock sign is decided.
     */
    private function create(
        int $tenantId,
        int $storeId,
        int $productId,
        string $movementType,
        string $qty,
        ?string $source = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        $qty = $this->normalize($qty);

        if (bccomp($qty, '0', self::SCALE) <= 0) {
            throw ValidationException::withMessages([
                'qty' => 'Quantity must be greater than zero.',
            ]);
        }

        $sign = InventoryMovement::signFor($movementType);
        if ($sign === 0) {
            throw ValidationException::withMessages([
                'movement_type' => 'Unknown inventory movement type.',
            ]);
        }

        $signedQty = $sign < 0 ? bcmul($qty, '-1', self::SCALE) : $qty;

        return InventoryMovement::create([
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'product_id' => $productId,
            'movement_type' => $movementType,
            'qty' => $qty,
            'signed_qty' => $signedQty,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'source' => $source,
            'notes' => $notes,
            'created_by' => $createdBy,
        ]);
    }

    private function assertStoreOwnedByTenant(int $tenantId, int $storeId): void
    {
        $owned = Store::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($storeId)
            ->exists();

        if (! $owned) {
            throw ValidationException::withMessages([
                'store_id' => 'The selected store does not belong to this tenant.',
            ]);
        }
    }

    private function assertProductOwnedByTenant(int $tenantId, int $productId): void
    {
        $owned = Product::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($productId)
            ->exists();

        if (! $owned) {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product does not belong to this tenant.',
            ]);
        }
    }

    private function normalize(string $value): string
    {
        return bcadd($value, '0', self::SCALE);
    }
}
