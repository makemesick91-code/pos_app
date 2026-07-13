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
        assertTrue(db.getPendingOrFailed(10).isNotEmpty())
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
        assertTrue(db.getPendingOrFailed(10).isNotEmpty())

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
        assertEquals(0, db.getPendingOrFailed(10).size)
    }
}
