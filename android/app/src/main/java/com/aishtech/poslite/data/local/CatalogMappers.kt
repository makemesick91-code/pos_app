package com.aishtech.poslite.data.local

import com.aishtech.poslite.data.local.entity.LocalProductCategoryEntity
import com.aishtech.poslite.data.local.entity.LocalProductEntity
import com.aishtech.poslite.data.remote.dto.CategoryDto
import com.aishtech.poslite.data.remote.dto.ProductDto

/**
 * Pure (framework-free) mapping from sync DTOs to Room entities so it can be
 * unit-tested on the JVM. Decimal-string prices are parsed defensively, and
 * effective_selling_price falls back to selling_price when absent.
 */
object CatalogMappers {

    fun toEntity(dto: ProductDto, syncedAt: Long): LocalProductEntity {
        val selling = dto.sellingPrice.toPriceOrZero()
        val effective = dto.effectiveSellingPrice?.toPriceOrNull() ?: selling
        return LocalProductEntity(
            id = dto.id,
            categoryId = dto.categoryId,
            sku = dto.sku,
            barcode = dto.barcode,
            name = dto.name?.trim().orEmpty(),
            unit = dto.unit,
            sellingPrice = selling,
            effectiveSellingPrice = effective,
            isStockTracked = dto.isStockTracked,
            isActive = dto.isActive,
            updatedAt = dto.updatedAt,
            lastSyncedAt = syncedAt,
        )
    }

    fun toEntity(dto: CategoryDto, syncedAt: Long): LocalProductCategoryEntity =
        LocalProductCategoryEntity(
            id = dto.id,
            name = dto.name?.trim().orEmpty(),
            sortOrder = dto.sortOrder,
            isActive = dto.isActive,
            updatedAt = dto.updatedAt,
            lastSyncedAt = syncedAt,
        )

    fun toProductEntities(dtos: List<ProductDto>, syncedAt: Long): List<LocalProductEntity> =
        dtos.map { toEntity(it, syncedAt) }

    fun toCategoryEntities(dtos: List<CategoryDto>, syncedAt: Long): List<LocalProductCategoryEntity> =
        dtos.map { toEntity(it, syncedAt) }

    private fun String?.toPriceOrNull(): Double? = this?.trim()?.toDoubleOrNull()
    private fun String?.toPriceOrZero(): Double = toPriceOrNull() ?: 0.0
}
