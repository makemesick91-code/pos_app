<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductStorePriceRequest;
use App\Http\Requests\Api\V1\UpdateProductStorePriceRequest;
use App\Http\Resources\Api\V1\ProductStorePriceResource;
use App\Models\ProductStorePrice;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Tenant-isolated, store-scoped CRUD for product price overrides. Every query
 * is scoped to the authenticated tenant; show/update/delete verify ownership
 * and 404 otherwise.
 */
class ProductStorePriceController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ProductStorePrice::query()
            ->forTenant($this->context->tenantId())
            ->orderBy('id');

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->query('store_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->query('product_id'));
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return ProductStorePriceResource::collection($query->get());
    }

    public function store(StoreProductStorePriceRequest $request): JsonResponse
    {
        $price = ProductStorePrice::create([
            'tenant_id' => $this->context->tenantId(),
            'store_id' => $request->input('store_id'),
            'product_id' => $request->input('product_id'),
            'selling_price' => $request->input('selling_price'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return ProductStorePriceResource::make($price)
            ->response()
            ->setStatusCode(201);
    }

    public function show(ProductStorePrice $productStorePrice): ProductStorePriceResource
    {
        $this->authorizeTenant($productStorePrice);

        return ProductStorePriceResource::make($productStorePrice);
    }

    public function update(UpdateProductStorePriceRequest $request, ProductStorePrice $productStorePrice): ProductStorePriceResource
    {
        $this->authorizeTenant($productStorePrice);

        $productStorePrice->fill($request->only([
            'selling_price',
            'is_active',
        ]));
        $productStorePrice->save();

        return ProductStorePriceResource::make($productStorePrice);
    }

    public function destroy(ProductStorePrice $productStorePrice): JsonResponse
    {
        $this->authorizeTenant($productStorePrice);

        $productStorePrice->update(['is_active' => false]);

        return response()->json([
            'message' => 'Store price override deactivated.',
            'id' => $productStorePrice->id,
            'is_active' => false,
        ]);
    }

    private function authorizeTenant(ProductStorePrice $price): void
    {
        abort_unless(
            (int) $price->tenant_id === (int) $this->context->tenantId(),
            404
        );
    }
}
