package com.aishtech.poslite

import com.aishtech.poslite.data.remote.dto.SaleResponse
import com.aishtech.poslite.data.repository.OfflineSaleRepository
import com.aishtech.poslite.feature.cashier.CartItem
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Test
import retrofit2.Response

/**
 * Sprint 7 — the queued offline sale maps to the backend contract correctly:
 * source ANDROID_OFFLINE, the client reference for idempotency, CASH payment,
 * and no forged totals (only line items + tendered cash are sent).
 */
class OfflineSaleMappingTest {

    @Test
    fun `sync sends source offline, client reference, and cash only`() = runTest {
        val db = FakeOfflineDb()
        val api = FakeSyncApi(listOf(Response.success(SaleResponse(data = sampleSale()))))
        val repository = OfflineSaleRepository(
            offlineSaleDao = db,
            offlineSaleItemDao = db,
            api = api,
            referenceProvider = { "map-ref-1" },
            clock = { 1_000L },
        )

        repository.createOfflineCashSale(
            items = listOf(CartItem(5L, "Nasi", 12000.0, 3)),
            paidAmount = 40000L,
        )
        repository.syncPending()

        val request = api.capturedRequests.single()
        assertEquals("ANDROID_OFFLINE", request.source)
        assertEquals("map-ref-1", request.clientReference)
        assertEquals("CASH", request.payment.method)
        assertEquals("40000.00", request.payment.paidAmount)
        assertEquals(1, request.items.size)
        assertEquals(5L, request.items.first().productId)
        assertEquals(3, request.items.first().qty)
        // client_created_at carries the on-device timestamp for the offline sale.
        assertEquals("1970-01-01T00:00:01Z", request.clientCreatedAt)
    }
}
