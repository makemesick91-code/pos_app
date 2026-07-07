package com.aishtech.poslite.data.local.entity

import androidx.room.Entity
import androidx.room.Index
import androidx.room.PrimaryKey
import com.aishtech.poslite.data.local.OfflineSyncStatus

/**
 * A CASH sale rung up while offline and queued for sync (Sprint 7).
 *
 * `clientReference` is a device-generated UUID that makes the eventual submit
 * idempotent — the backend dedupes retries per (tenant, store, clientReference).
 * Local totals exist for the offline draft receipt/UI only; once synced the
 * backend response is authoritative. QRIS is never stored here (online-only).
 */
@Entity(
    tableName = "offline_sales",
    indices = [
        Index(value = ["clientReference"], unique = true),
        Index(value = ["syncStatus"]),
    ],
)
data class LocalOfflineSaleEntity(
    @PrimaryKey(autoGenerate = true) val localId: Long = 0,
    val clientReference: String,
    val storeId: Long?,
    val saleDate: String,
    val subtotal: Double,
    val discountTotal: Double,
    val taxTotal: Double,
    val grandTotal: Double,
    val paidAmount: Double,
    val changeAmount: Double,
    val syncStatus: String = OfflineSyncStatus.PENDING,
    val syncAttemptCount: Int = 0,
    val lastSyncError: String? = null,
    val serverSaleId: Long? = null,
    val serverInvoiceNumber: String? = null,
    val createdAt: Long,
    val updatedAt: Long,
    val lastAttemptedAt: Long? = null,
    val syncedAt: Long? = null,
)
