package com.aishtech.poslite.data.remote.dto

import com.google.gson.annotations.SerializedName

/**
 * Sprint 8 — inventory stock DTOs. Stock is authoritative on the backend
 * (derived from the ledger); the app only displays it. `current_stock` is a
 * decimal string on the wire (Laravel decimal cast, e.g. "12.00") and is parsed
 * defensively for display.
 */

/** GET /api/v1/inventory/current-stock */
data class CurrentStockResponseDto(
    @SerializedName("data") val data: List<CurrentStockItemDto> = emptyList(),
    @SerializedName("meta") val meta: MetaDto? = null,
)

data class CurrentStockItemDto(
    @SerializedName("product_id") val productId: Long,
    @SerializedName("sku") val sku: String?,
    @SerializedName("barcode") val barcode: String?,
    @SerializedName("name") val name: String?,
    @SerializedName("unit") val unit: String?,
    @SerializedName("is_stock_tracked") val isStockTracked: Boolean = false,
    @SerializedName("current_stock") val currentStock: String?,
)

/** GET /api/v1/inventory/products/{product}/stock */
data class ProductStockResponseDto(
    @SerializedName("data") val data: ProductStockDto?,
    @SerializedName("meta") val meta: MetaDto? = null,
)

data class ProductStockDto(
    @SerializedName("product_id") val productId: Long,
    @SerializedName("sku") val sku: String?,
    @SerializedName("name") val name: String?,
    @SerializedName("unit") val unit: String?,
    @SerializedName("is_stock_tracked") val isStockTracked: Boolean = false,
    @SerializedName("current_stock") val currentStock: String?,
)

/** GET /api/v1/inventory/movements (optional listing). */
data class InventoryMovementDto(
    @SerializedName("id") val id: Long,
    @SerializedName("product_id") val productId: Long,
    @SerializedName("movement_type") val movementType: String?,
    @SerializedName("qty") val qty: String?,
    @SerializedName("signed_qty") val signedQty: String?,
    @SerializedName("created_at") val createdAt: String?,
)
