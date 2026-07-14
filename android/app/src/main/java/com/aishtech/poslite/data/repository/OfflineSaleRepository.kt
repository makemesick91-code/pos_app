package com.aishtech.poslite.data.repository

import com.aishtech.poslite.core.money.RupiahMoney
import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.data.local.OfflineSyncStatus
import com.aishtech.poslite.data.local.dao.OfflineSaleDao
import com.aishtech.poslite.data.local.dao.OfflineSaleItemDao
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleEntity
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleItemEntity
import com.aishtech.poslite.data.remote.dto.CashPaymentRequestDto
import com.aishtech.poslite.data.remote.dto.CreateSaleItemRequestDto
import com.aishtech.poslite.data.remote.dto.CreateSaleRequestDto
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.TimeZone
import java.util.UUID

/**
 * Owns offline CASH sales end to end (Sprint 7):
 *
 *  1. [createOfflineCashSale] snapshots the cart into the local queue with a
 *     device-generated client reference. The cart is NEVER cleared here — that
 *     is a UI decision made only after this returns [SaveResult.Saved].
 *  2. [syncPending] replays queued sales to the backend as ANDROID_OFFLINE CASH
 *     submits. A success/idempotent-replay marks the row SYNCED; a transient
 *     error keeps it FAILED (retryable); a validation/conflict marks CONFLICT.
 *
 * QRIS is intentionally impossible here — offline is CASH-only.
 */
class OfflineSaleRepository(
    private val offlineSaleDao: OfflineSaleDao,
    private val offlineSaleItemDao: OfflineSaleItemDao,
    private val api: PosApiService,
    private val referenceProvider: () -> String = { UUID.randomUUID().toString() },
    private val clock: () -> Long = { System.currentTimeMillis() },
) {

    sealed class SaveResult {
        data class Saved(val localId: Long, val clientReference: String) : SaveResult()
        data class Error(val message: String) : SaveResult()
    }

    /** Outcome of syncing one queued sale. */
    enum class SyncOutcome { SYNCED, FAILED, CONFLICT }

    data class SyncSummary(val synced: Int, val failed: Int, val conflicts: Int) {
        val attempted: Int get() = synced + failed + conflicts
    }

    /**
     * Snapshot the cart into the local offline queue. Returns [SaveResult.Saved]
     * only when the sale AND its items are persisted; on any failure returns
     * [SaveResult.Error] and stores nothing (so the caller keeps the cart).
     *
     * UIX-8C-04 (UIX8C-R097/R109) — [clientReference] may be supplied so a
     * governed online→offline fallback reuses the SAME stable idempotency key that
     * the online attempt used (rather than minting a new one). The save is
     * idempotent on that key: if a row already exists (a repeated fallback / rapid
     * tap), the existing row is returned and NO duplicate is created.
     */
    suspend fun createOfflineCashSale(
        items: List<com.aishtech.poslite.feature.cashier.CartItem>,
        paidAmount: Long,
        storeId: Long? = null,
        clientReference: String? = null,
    ): SaveResult {
        if (items.isEmpty()) {
            return SaveResult.Error("Keranjang kosong.")
        }

        // UIX-8 — the draft total is computed integer-exact in whole rupiah; the
        // Room entity still stores Double columns, so the integer value is
        // projected to Double at THIS single persistence boundary only.
        val subtotal = RupiahMoney.subtotal(items.map { it.lineTotalRupiah })
        if (!RupiahMoney.isSufficient(paidAmount, subtotal)) {
            return SaveResult.Error("Uang dibayar kurang dari total.")
        }
        val change = RupiahMoney.change(paidAmount, subtotal)

        val reference = clientReference ?: referenceProvider()

        // UIX8C-R109 — idempotent fallback: if this reference is already queued,
        // reconcile to the existing durable row instead of creating a second one.
        offlineSaleDao.findByClientReference(reference)?.let {
            return SaveResult.Saved(it.localId, it.clientReference)
        }

        val ts = clock()
        val sale = LocalOfflineSaleEntity(
            clientReference = reference,
            storeId = storeId,
            saleDate = isoUtc(ts),
            subtotal = subtotal.toDouble(),
            discountTotal = 0.0,
            taxTotal = 0.0,
            grandTotal = subtotal.toDouble(),
            paidAmount = paidAmount.toDouble(),
            changeAmount = change.toDouble(),
            syncStatus = OfflineSyncStatus.PENDING,
            syncAttemptCount = 0,
            createdAt = ts,
            updatedAt = ts,
        )

        val itemEntities = items.map {
            LocalOfflineSaleItemEntity(
                offlineSaleLocalId = 0,
                productId = it.productId,
                productName = it.name,
                qty = it.quantity,
                unitPrice = it.unitPrice,
                discount = 0.0,
                subtotal = it.lineTotal,
            )
        }

        return try {
            val localId = offlineSaleDao.insertOfflineSaleWithItems(sale, itemEntities)
            SaveResult.Saved(localId, reference)
        } catch (e: Exception) {
            // UIX8C-R109 — a concurrent insert (rapid tap) that lost the race to the
            // unique clientReference index reconciles to the winning row rather than
            // failing (which would falsely keep the cart on an already-durable save).
            offlineSaleDao.findByClientReference(reference)?.let {
                return SaveResult.Saved(it.localId, it.clientReference)
            }
            SaveResult.Error(e.message ?: "Gagal menyimpan transaksi offline.")
        }
    }

    /**
     * Replay up to [limit] eligible offline sales to the backend. FAILED rows
     * that have exhausted [MAX_SYNC_ATTEMPTS] are no longer auto-retried (they
     * remain FAILED and visible) so a poison row cannot starve the queue.
     */
    suspend fun syncPending(limit: Int = 10): SyncSummary {
        val queue = offlineSaleDao.getPendingOrFailed(limit, MAX_SYNC_ATTEMPTS)
        var synced = 0
        var failed = 0
        var conflicts = 0

        for (sale in queue) {
            when (syncOne(sale)) {
                SyncOutcome.SYNCED -> synced++
                SyncOutcome.FAILED -> failed++
                SyncOutcome.CONFLICT -> conflicts++
            }
        }

        return SyncSummary(synced = synced, failed = failed, conflicts = conflicts)
    }

    private suspend fun syncOne(sale: LocalOfflineSaleEntity): SyncOutcome {
        offlineSaleDao.markSyncing(sale.localId, clock())

        val items = offlineSaleItemDao.getItemsForSale(sale.localId)
        val request = CreateSaleRequestDto(
            items = items.map {
                CreateSaleItemRequestDto(
                    productId = it.productId,
                    qty = it.qty,
                    discount = amount(it.discount),
                )
            },
            payment = CashPaymentRequestDto(paidAmount = amount(sale.paidAmount)),
            source = SOURCE_ANDROID_OFFLINE,
            clientReference = sale.clientReference,
            clientCreatedAt = sale.saleDate,
        )

        return try {
            val response = api.createSale(request)
            val body = response.body()
            when {
                response.isSuccessful && body != null -> {
                    // Covers both a fresh create and an idempotent replay (200):
                    // either way the server holds the authoritative sale now.
                    offlineSaleDao.markSynced(
                        localId = sale.localId,
                        serverSaleId = body.data.id,
                        invoiceNumber = body.data.invoiceNumber,
                        syncedAt = clock(),
                    )
                    SyncOutcome.SYNCED
                }
                response.code() == 422 || response.code() == 409 -> {
                    offlineSaleDao.markConflict(sale.localId, "HTTP ${response.code()}", clock())
                    SyncOutcome.CONFLICT
                }
                else -> {
                    offlineSaleDao.markFailed(sale.localId, "HTTP ${response.code()}", clock())
                    SyncOutcome.FAILED
                }
            }
        } catch (e: Exception) {
            offlineSaleDao.markFailed(sale.localId, e.message ?: "network error", clock())
            SyncOutcome.FAILED
        }
    }

    suspend fun pendingCount(): Int = offlineSaleDao.countPending()

    suspend fun failedCount(): Int = offlineSaleDao.countFailed()

    /**
     * UIX-8B — recent local sales for the transaction-history screen, newest
     * first and bounded (UIX8B-R059/R062). The local queue is the device's
     * transaction record; each row appears once.
     */
    suspend fun recentSales(limit: Int = 100): List<LocalOfflineSaleEntity> =
        offlineSaleDao.getRecent(limit)

    private fun amount(value: Double): String = String.format(Locale.US, "%.2f", value)

    private fun isoUtc(epochMillis: Long): String {
        val format = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss'Z'", Locale.US)
        format.timeZone = TimeZone.getTimeZone("UTC")
        return format.format(Date(epochMillis))
    }

    companion object {
        const val SOURCE_ANDROID_OFFLINE = "ANDROID_OFFLINE"

        /**
         * UIX-8 bounded retry — the maximum automatic sync attempts for a FAILED
         * offline sale before it stops being auto-retried (it stays FAILED and
         * visible for manual attention). Chosen to tolerate transient outages
         * while preventing a poison row from starving the sync queue forever.
         */
        const val MAX_SYNC_ATTEMPTS = 5
    }
}
