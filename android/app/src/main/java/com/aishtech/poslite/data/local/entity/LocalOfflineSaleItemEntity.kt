package com.aishtech.poslite.data.local.entity

import androidx.room.Entity
import androidx.room.Index
import androidx.room.PrimaryKey

/**
 * A snapshotted line of an offline sale (Sprint 7). Product name/price are
 * captured at ring-up time so the offline draft receipt is stable even if the
 * catalog changes before sync. Deleted with its parent sale.
 */
@Entity(
    tableName = "offline_sale_items",
    indices = [Index(value = ["offlineSaleLocalId"])],
)
data class LocalOfflineSaleItemEntity(
    @PrimaryKey(autoGenerate = true) val localId: Long = 0,
    val offlineSaleLocalId: Long,
    val productId: Long,
    val productName: String,
    val sku: String? = null,
    val barcode: String? = null,
    val unit: String? = null,
    val qty: Int,
    val unitPrice: Double,
    val discount: Double,
    val subtotal: Double,
)
