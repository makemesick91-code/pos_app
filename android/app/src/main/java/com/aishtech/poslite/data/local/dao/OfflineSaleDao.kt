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

    /**
     * Rows eligible for a (re)sync attempt: PENDING, orphaned in-flight SYNCING
     * (UIX7-R009/R012), and FAILED rows still under the retry cap.
     *
     * A sale is set SYNCING immediately before the network call, so a process
     * death between [markSyncing] and the server response would otherwise strand
     * it in SYNCING forever and silently lose the transaction. Replaying it is
     * safe because the submit is idempotent on the device-generated
     * clientReference; the server dedupes a genuine in-flight duplicate.
     *
     * UIX-8 (bounded retry) — a FAILED row is only eligible while
     * `syncAttemptCount < maxAttempts`. Without this cap a permanently-failing
     * ("poison") row, ordered oldest-first, is re-selected on every sync and
     * consumes the LIMIT window, starving newer sales from ever syncing. Past the
     * cap the row STAYS FAILED (still counted by [countFailed] and visible to the
     * cashier for manual attention) — it is not silently dropped, it just stops
     * auto-retrying. PENDING and orphaned SYNCING rows are never capped.
     */
    @Query(
        """
        SELECT * FROM offline_sales
        WHERE syncStatus IN ('PENDING', 'SYNCING')
           OR (syncStatus = 'FAILED' AND syncAttemptCount < :maxAttempts)
        ORDER BY createdAt ASC
        LIMIT :limit
        """
    )
    abstract suspend fun getPendingOrFailed(limit: Int, maxAttempts: Int): List<LocalOfflineSaleEntity>

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

    /**
     * UIX-8B — the most recent local sales for the transaction-history screen,
     * newest first and bounded (UIX8B-R059/R062). Rows are already tenant/device
     * scoped by the per-tenant Room database (UIX-7); each localId appears once.
     */
    @Query("SELECT * FROM offline_sales ORDER BY createdAt DESC LIMIT :limit")
    abstract suspend fun getRecent(limit: Int): List<LocalOfflineSaleEntity>
}
