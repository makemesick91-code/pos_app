<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductCategoryRequest;
use App\Http\Requests\Api\V1\UpdateProductCategoryRequest;
use App\Http\Resources\Api\V1\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Tenant-isolated CRUD for product categories. Every query is scoped to the
 * authenticated tenant; show/update/delete verify ownership and 404 otherwise.
 */
class ProductCategoryController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ProductCategory::query()
            ->forTenant($this->context->tenantId())
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->query('store_id'));
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return ProductCategoryResource::collection($query->get());
    }

    public function store(StoreProductCategoryRequest $request): JsonResponse
    {
        $category = ProductCategory::create([
            'tenant_id' => $this->context->tenantId(),
            'store_id' => $request->input('store_id'),
            'name' => $request->input('name'),
            'sort_order' => $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return ProductCategoryResource::make($category)
            ->response()
            ->setStatusCode(201);
    }

    public function show(ProductCategory $productCategory): ProductCategoryResource
    {
        $this->authorizeTenant($productCategory);

        return ProductCategoryResource::make($productCategory);
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory): ProductCategoryResource
    {
        $this->authorizeTenant($productCategory);

        $productCategory->fill($request->only([
            'name',
            'store_id',
            'sort_order',
            'is_active',
        ]));
        $productCategory->save();

        return ProductCategoryResource::make($productCategory);
    }

    public function destroy(ProductCategory $productCategory): JsonResponse
    {
        $this->authorizeTenant($productCategory);

        // Soft retire: keep the row but mark it inactive (foundation-safe).
        $productCategory->update(['is_active' => false]);

        return response()->json([
            'message' => 'Category deactivated.',
            'id' => $productCategory->id,
            'is_active' => false,
        ]);
    }

    private function authorizeTenant(ProductCategory $category): void
    {
        abort_unless(
            (int) $category->tenant_id === (int) $this->context->tenantId(),
            404
        );
    }
}
