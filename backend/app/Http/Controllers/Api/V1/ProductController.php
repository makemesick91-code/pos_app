<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Tenant-isolated CRUD for products. Every query is scoped to the authenticated
 * tenant; show/update/delete verify ownership and 404 otherwise.
 */
class ProductController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()
            ->forTenant($this->context->tenantId())
            ->orderBy('name');

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->query('store_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->query('category_id'));
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->filled('q')) {
            $query->search((string) $request->query('q'));
        }

        return ProductResource::collection($query->get());
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create([
            'tenant_id' => $this->context->tenantId(),
            'store_id' => $request->input('store_id'),
            'category_id' => $request->input('category_id'),
            'sku' => $request->input('sku'),
            'barcode' => $request->input('barcode'),
            'name' => $request->input('name'),
            'unit' => $request->input('unit', 'pcs'),
            'cost_price' => $request->input('cost_price'),
            'selling_price' => $request->input('selling_price'),
            'is_stock_tracked' => $request->boolean('is_stock_tracked', true),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return ProductResource::make($product)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        $this->authorizeTenant($product);

        return ProductResource::make($product);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $this->authorizeTenant($product);

        $product->fill($request->only([
            'store_id',
            'category_id',
            'sku',
            'barcode',
            'name',
            'unit',
            'cost_price',
            'selling_price',
            'is_stock_tracked',
            'is_active',
        ]));
        $product->save();

        return ProductResource::make($product);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorizeTenant($product);

        $product->update(['is_active' => false]);

        return response()->json([
            'message' => 'Product deactivated.',
            'id' => $product->id,
            'is_active' => false,
        ]);
    }

    private function authorizeTenant(Product $product): void
    {
        abort_unless(
            (int) $product->tenant_id === (int) $this->context->tenantId(),
            404
        );
    }
}
