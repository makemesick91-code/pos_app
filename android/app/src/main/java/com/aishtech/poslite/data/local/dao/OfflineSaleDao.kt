package com.aishtech.poslite.data.local.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.Query
import androidx.room.Transaction
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleEntity
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleItemEntity

/**
 * DAO for the offline sale queue (Sprint 7). Status transitions are single-row
 * UPDATEs so a sync attempt can never partially corrupt the queue, and the
 * sale+items insert is wrapped in one transaction.
 *
 * Abstract class (not interface) so [insertOfflineSaleWithItems] can compose the
 * two inserts atomically and so unit tests can subclass it with an in-memory
 * fake without a Room runtime.
 */
@Dao
abstract class OfflineSaleDao {

    @Insert
    abstract suspend fun insertSale(sale: LocalOfflineSaleEntity): Long

    @Insert
    abstract suspend fun insertItems(items: List<LocalOfflineSaleItemEntity>)

    /** Persist a sale and its snapshotted items atomically. Returns the localId. */
    @Transaction
    open suspend fun insertOfflineSaleWithItems(
        sale: LocalOfflineSaleEntity,
        items: List<LocalOfflineSaleItemEntity>,
    ): Long {
        val localId = insertSale(sale)
        if (items.isNotEmpty()) {
            insertItems(items.map { it.copy(offlineSaleLocalId = localId) })
        }
        return localId
    }

    @Query(
        """
        SELECT * FROM offline_sales
        WHERE syncStatus IN ('PENDING', 'FAILED')
        ORDER BY createdAt ASC
        LIMIT :limit
        """
    )
    abstract suspend fun getPendingOrFailed(limit: Int): List<LocalOfflineSaleEntity>

    @Query("SELECT * FROM offline_sales WHERE localId = :localId LIMIT 1")
    abstract suspend fun getOfflineSaleWithItems(localId: Long): LocalOfflineSaleEntity?

    @Query(
        "UPDATE offline_sales SET syncStatus = 'SYNCING', lastAttemptedAt = :attemptedAt, " +
            "updatedAt = :attemptedAt WHERE localId = :localId"
    )
    abstract suspend fun markSyncing(localId: Long, attemptedAt: Long)

    @Query(
        "UPDATE offline_sales SET syncStatus = 'SYNCED', serverSaleId = :serverSaleId, " +
            "serverInvoiceNumber = :invoiceNumber, syncedAt = :syncedAt, lastSyncError = NULL, " +
            "updatedAt = :syncedAt WHERE localId = :localId"
    )
    abstract suspend fun markSynced(localId: Long, serverSaleId: Long, invoiceNumber: String?, syncedAt: Long)

    @Query(
        "UPDATE offline_sales SET syncStatus = 'FAILED', syncAttemptCount = syncAttemptCount + 1, " +
            "lastSyncError = :error, lastAttemptedAt = :attemptedAt, updatedAt = :attemptedAt WHERE localId = :localId"
    )
    abstract suspend fun markFailed(localId: Long, error: String?, attemptedAt: Long)

    @Query(
        "UPDATE offline_sales SET syncStatus = 'CONFLICT', syncAttemptCount = syncAttemptCount + 1, " +
            "lastSyncError = :error, lastAttemptedAt = :attemptedAt, updatedAt = :attemptedAt WHERE localId = :localId"
    )
    abstract suspend fun markConflict(localId: Long, error: String?, attemptedAt: Long)

    @Query("SELECT COUNT(*) FROM offline_sales WHERE syncStatus IN ('PENDING', 'SYNCING')")
    abstract suspend fun countPending(): Int

    @Query("SELECT COUNT(*) FROM offline_sales WHERE syncStatus = 'FAILED'")
    abstract suspend fun countFailed(): Int
}
