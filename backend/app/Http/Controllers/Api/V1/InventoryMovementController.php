<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexInventoryMovementRequest;
use App\Http\Resources\Api\V1\InventoryMovementResource;
use App\Models\InventoryMovement;
use App\Support\TenantContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * A basic, tenant-isolated inventory movement listing (not an advanced report).
 * Every query is scoped to the authenticated tenant so cross-tenant ledger rows
 * are never returned. See Sprint 8 evidence.
 */
class InventoryMovementController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function index(IndexInventoryMovementRequest $request): AnonymousResourceCollection
    {
        $tenantId = (int) $this->context->tenantId();

        $query = InventoryMovement::query()
            ->forTenant($tenantId)
            ->orderByDesc('id');

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->input('store_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->input('product_id'));
        }

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->input('movement_type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }

        $limit = (int) $request->input('limit', 50);
        $limit = max(1, min($limit, 200));

        return InventoryMovementResource::collection($query->limit($limit)->get())->additional([
            'meta' => [
                'tenant_id' => $tenantId,
                'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
            ],
        ]);
    }
}
