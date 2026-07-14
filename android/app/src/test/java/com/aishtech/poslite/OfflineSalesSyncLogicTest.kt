package com.aishtech.poslite

import com.aishtech.poslite.data.local.OfflineSyncStatus
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleEntity
import com.aishtech.poslite.data.remote.dto.SaleResponse
import com.aishtech.poslite.data.repository.OfflineSaleRepository
import kotlinx.coroutines.test.runTest
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import retrofit2.Response

/**
 * Sprint 7 — offline sync outcome logic. A pending sale becomes SYNCED on
 * success (including idempotent replays), stays retryable on transient errors,
 * and becomes CONFLICT on a permanent rejection.
 */
class OfflineSalesSyncLogicTest {

    private fun pendingSale(ref: String = "ref-1"): LocalOfflineSaleEntity =
        LocalOfflineSaleEntity(
            clientReference = ref,
            storeId = 1L,
            saleDate = "2026-07-07T10:00:00Z",
            subtotal = 20000.0,
            discountTotal = 0.0,
            taxTotal = 0.0,
            grandTotal = 20000.0,
            paidAmount = 25000.0,
            changeAmount = 5000.0,
            syncStatus = OfflineSyncStatus.PENDING,
            syncAttemptCount = 0,
            createdAt = 1L,
            updatedAt = 1L,
        )

    private fun repo(db: FakeOfflineDb, responses: List<Response<SaleResponse>>): OfflineSaleRepository =
        OfflineSaleRepository(
            offlineSaleDao = db,
            offlineSaleItemDao = db,
            api = FakeSyncApi(responses),
            clock = { 2_000L },
        )

    private fun serverError(code: Int): Response<SaleResponse> =
        Response.error(code, "{}".toResponseBody("application/json".toMediaType()))

    @Test
    fun `successful response marks the sale SYNCED`() = runTest {
        val db = FakeOfflineDb()
        db.insertOfflineSaleWithItems(pendingSale(), emptyList())

        val summary = repo(db, listOf(Response.success(SaleResponse(data = sampleSale(id = 99))))).syncPending()

        assertEquals(1, summary.synced)
        val sale = db.sales.values.single()
        assertEquals(OfflineSyncStatus.SYNCED, sale.syncStatus)
        assertEquals(99L, sale.serverSaleId)
        assertEquals(2_000L, sale.syncedAt)
    }

    @Test
    fun `idempotent replay response still marks SYNCED`() = runTest {
        val db = FakeOfflineDb()
        db.insertOfflineSaleWithItems(pendingSale(), emptyList())

        val summary = repo(db, listOf(Response.success(replaySale(id = 7)))).syncPending()

        assertEquals(1, summary.synced)
        assertEquals(OfflineSyncStatus.SYNCED, db.sales.values.single().syncStatus)
    }

    @Test
    fun `transient server error preserves the sale as FAILED`() = runTest {
        val db = FakeOfflineDb()
        db.insertOfflineSaleWithItems(pendingSale(), emptyList())

        val summary = repo(db, listOf(serverError(500))).syncPending()

        assertEquals(1, summary.failed)
        val sale = db.sales.values.single()
        assertEquals(OfflineSyncStatus.FAILED, sale.syncStatus)
        assertEquals(1, sale.syncAttemptCount)
        // Still in the queue for retry.
        assertTrue(db.getPendingOrFailed(10, OfflineSaleRepository.MAX_SYNC_ATTEMPTS).isNotEmpty())
    }

    @Test
    fun `validation rejection marks the sale CONFLICT`() = runTest {
        val db = FakeOfflineDb()
        db.insertOfflineSaleWithItems(pendingSale(), emptyList())

        val summary = repo(db, listOf(serverError(422))).syncPending()

        assertEquals(1, summary.conflicts)
        assertEquals(OfflineSyncStatus.CONFLICT, db.sales.values.single().syncStatus)
    }

    @Test
    fun `orphaned in-flight SYNCING sale is recovered and synced on the next run`() = runTest {
        // Simulate a crash mid-attempt: the row was marked SYNCING before the
        // server responded, so it is stranded. Before UIX-7 it was excluded from
        // the retry queue and silently lost (UIX7-R009/R012).
        val db = FakeOfflineDb()
        db.insertOfflineSaleWithItems(pendingSale("stuck"), emptyList())
        db.markSyncing(db.sales.keys.first(), attemptedAt = 500L)
        assertEquals(OfflineSyncStatus.SYNCING, db.sales.values.single().syncStatus)

        // The recovery queue must pick it up...
        assertTrue(db.getPendingOrFailed(10, OfflineSaleRepository.MAX_SYNC_ATTEMPTS).isNotEmpty())

        // ...and a successful replay drives it to SYNCED with the server id.
        val summary = repo(db, listOf(Response.success(SaleResponse(data = sampleSale(id = 77))))).syncPending()
        assertEquals(1, summary.synced)
        val sale = db.sales.values.single()
        assertEquals(OfflineSyncStatus.SYNCED, sale.syncStatus)
        assertEquals(77L, sale.serverSaleId)
    }

    @Test
    fun `only pending and failed sales are attempted`() = runTest {
        val db = FakeOfflineDb()
        // One pending, and one that fails then should be retried on next run.
        db.insertOfflineSaleWithItems(pendingSale("a"), emptyList())

        val repository = repo(db, listOf(serverError(500)))
        repository.syncPending()
        assertEquals(1, db.countFailed())

        // A synced sale is never re-attempted.
        db.markSynced(db.sales.keys.first(), 1L, "INV", 3_000L)
        assertEquals(0, db.getPendingOrFailed(10, OfflineSaleRepository.MAX_SYNC_ATTEMPTS).size)
    }

    // UIX-8 bounded retry — a poison row that has exhausted the attempt cap is no
    // longer auto-retried and cannot starve the queue, yet it stays FAILED and
    // visible (not silently dropped); a newer pending sale still syncs.
    @Test
    fun `failed sale past the retry cap is excluded but newer sales still sync`() = runTest {
        val db = FakeOfflineDb()
        // A poison row already at the cap (oldest → would otherwise head the queue).
        db.insertOfflineSaleWithItems(
            pendingSale("poison").copy(
                syncStatus = OfflineSyncStatus.FAILED,
                syncAttemptCount = OfflineSaleRepository.MAX_SYNC_ATTEMPTS,
                createdAt = 1L,
            ),
            emptyList(),
        )
        // A fresh pending sale created later.
        db.insertOfflineSaleWithItems(pendingSale("fresh").copy(createdAt = 2L), emptyList())

        // The capped row is not eligible; only the fresh one is.
        val eligible = db.getPendingOrFailed(10, OfflineSaleRepository.MAX_SYNC_ATTEMPTS)
        assertEquals(1, eligible.size)
        assertEquals("fresh", eligible.single().clientReference)

        val summary = repo(db, listOf(Response.success(SaleResponse(data = sampleSale(id = 55))))).syncPending()
        assertEquals(1, summary.synced)

        // The poison row is untouched and STILL FAILED (visible, not dropped).
        val poison = db.sales.values.first { it.clientReference == "poison" }
        assertEquals(OfflineSyncStatus.FAILED, poison.syncStatus)
        assertEquals(1, db.countFailed())
    }
}
