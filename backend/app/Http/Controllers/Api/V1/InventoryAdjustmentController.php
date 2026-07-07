<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInventoryAdjustmentRequest;
use App\Http\Resources\Api\V1\InventoryMovementResource;
use App\Services\Inventory\InventoryMovementService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Creates manual inventory adjustments (OPENING / ADJUSTMENT_IN /
 * ADJUSTMENT_OUT). SALE_OUT is never reachable here — the service rejects it —
 * so stock can only leave through a real sale. tenant_id comes from context;
 * store/product ownership is enforced by the request + service. signed_qty is
 * backend-computed. See Sprint 8 evidence.
 */
class InventoryAdjustmentController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly InventoryMovementService $inventory,
    ) {}

    public function store(StoreInventoryAdjustmentRequest $request): JsonResponse
    {
        $tenantId = (int) $this->context->tenantId();
        $storeId = $this->resolveStoreId($request->input('store_id'));

        $movement = $this->inventory->createAdjustment(
            tenantId: $tenantId,
            storeId: $storeId,
            productId: (int) $request->input('product_id'),
            movementType: (string) $request->input('movement_type'),
            qty: (string) $request->input('qty'),
            notes: $request->input('notes'),
            createdBy: $this->context->user()?->id,
        );

        return InventoryMovementResource::make($movement)
            ->additional(['meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION']])
            ->response()
            ->setStatusCode(201);
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
