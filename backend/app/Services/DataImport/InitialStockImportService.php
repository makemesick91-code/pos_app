<?php

namespace App\Services\DataImport;

use App\Models\InventoryMovement;
use App\Services\Inventory\InventoryMovementService;

class InitialStockImportService
{
    public function __construct(private readonly InventoryMovementService $inventory) {}

    public function apply(int $tenantId, ?int $branchId, array $row, ?int $createdBy = null, ?string $reference = null): array
    {
        $existing = InventoryMovement::query()
            ->where('tenant_id', $tenantId)
            ->where('store_id', $row['store_id'])
            ->where('product_id', $row['product_id'])
            ->where('movement_type', InventoryMovement::TYPE_OPENING)
            ->where('source', InventoryMovement::SOURCE_OPENING)
            ->where('notes', $reference)
            ->first();

        if ($existing !== null) {
            return ['action' => 'skipped', 'subject' => $existing];
        }

        $movement = $this->inventory->createOpeningMovement(
            tenantId: $tenantId,
            storeId: (int) $row['store_id'],
            productId: (int) $row['product_id'],
            qty: (string) $row['qty'],
            notes: $reference,
            createdBy: $createdBy,
        );

        return ['action' => 'created', 'subject' => $movement];
    }
}
