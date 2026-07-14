package com.aishtech.poslite

import com.aishtech.poslite.core.network.NetworkMonitor
import com.aishtech.poslite.data.remote.dto.SaleResponse
import com.aishtech.poslite.data.repository.OfflineSaleRepository
import com.aishtech.poslite.feature.cashier.CartItem
import com.aishtech.poslite.feature.qris.QrisOnlineOnlyGuard
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import retrofit2.Response

/**
 * Sprint 7 — QRIS is online-only, CASH is not. Blocks QRIS creation while
 * offline while confirming an offline CASH sale can still be saved locally.
 */
class QrisOnlineOnlyGuardTest {

    private class FakeNetworkMonitor(private val online: Boolean) : NetworkMonitor {
        override fun isOnline(): Boolean = online
    }

    @Test
    fun `qris is blocked when offline`() {
        val guard = QrisOnlineOnlyGuard(FakeNetworkMonitor(online = false))
        assertFalse(guard.canCreateQris())
        assertEquals("QRIS membutuhkan koneksi internet", QrisOnlineOnlyGuard.OFFLINE_MESSAGE)
    }

    @Test
    fun `qris is allowed when online`() {
        val guard = QrisOnlineOnlyGuard(FakeNetworkMonitor(online = true))
        assertTrue(guard.canCreateQris())
    }

    @Test
    fun `cash offline remains allowed when offline`() = runTest {
        val db = FakeOfflineDb()
        val repository = OfflineSaleRepository(
            offlineSaleDao = db,
            offlineSaleItemDao = db,
            api = FakeSyncApi(listOf(Response.success(SaleResponse(data = sampleSale())))),
            referenceProvider = { "cash-offline-ref" },
            clock = { 1_000L },
        )

        // Device is offline (QRIS blocked), but the CASH sale still saves locally.
        val guard = QrisOnlineOnlyGuard(FakeNetworkMonitor(online = false))
        assertFalse(guard.canCreateQris())

        val result = repository.createOfflineCashSale(
            items = listOf(CartItem(1L, "Kopi", 10000.0, 1)),
            paidAmount = 10000L,
        )
        assertTrue(result is OfflineSaleRepository.SaveResult.Saved)
        assertEquals(1, db.countPending())
    }
}
