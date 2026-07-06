package com.aishtech.poslite.data.local.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

/**
 * Locally cached product row (Room). Mirrors the backend sync payload with
 * prices resolved to numeric values. `lastSyncedAt` is device epoch millis.
 */
@Entity(tableName = "products")
data class LocalProductEntity(
    @PrimaryKey val id: Long,
    val categoryId: Long?,
    val sku: String?,
    val barcode: String?,
    val name: String,
    val unit: String?,
    val sellingPrice: Double,
    val effectiveSellingPrice: Double,
    val isStockTracked: Boolean,
    val isActive: Boolean,
    val updatedAt: String?,
    val lastSyncedAt: Long,
)
