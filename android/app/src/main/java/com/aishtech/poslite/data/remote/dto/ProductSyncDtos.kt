package com.aishtech.poslite.data.remote.dto

import com.google.gson.annotations.SerializedName

/**
 * GET /api/v1/sync/products response.
 *
 * Prices are decimal strings on the wire (Laravel `decimal:2` cast, e.g.
 * "10000.00"), so they are typed as String? and parsed defensively when
 * mapped to local entities.
 */
data class ProductSyncResponse(
    @SerializedName("data") val data: List<ProductDto> = emptyList(),
    @SerializedName("meta") val meta: MetaDto? = null,
)

data class ProductDto(
    @SerializedName("id") val id: Long,
    @SerializedName("category_id") val categoryId: Long?,
    @SerializedName("sku") val sku: String?,
    @SerializedName("barcode") val barcode: String?,
    @SerializedName("name") val name: String?,
    @SerializedName("unit") val unit: String?,
    @SerializedName("selling_price") val sellingPrice: String?,
    @SerializedName("effective_selling_price") val effectiveSellingPrice: String?,
    @SerializedName("is_stock_tracked") val isStockTracked: Boolean = false,
    @SerializedName("is_active") val isActive: Boolean = true,
    @SerializedName("updated_at") val updatedAt: String?,
)

/** GET /api/v1/sync/categories response. */
data class CategorySyncResponse(
    @SerializedName("data") val data: List<CategoryDto> = emptyList(),
    @SerializedName("meta") val meta: MetaDto? = null,
)

data class CategoryDto(
    @SerializedName("id") val id: Long,
    @SerializedName("name") val name: String?,
    @SerializedName("sort_order") val sortOrder: Int = 0,
    @SerializedName("is_active") val isActive: Boolean = true,
    @SerializedName("updated_at") val updatedAt: String?,
)
