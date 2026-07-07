package com.aishtech.poslite

import com.aishtech.poslite.data.remote.dto.SaleResponse
import com.aishtech.poslite.data.repository.CartRepository
import com.aishtech.poslite.data.repository.OfflineSaleRepository
import com.aishtech.poslite.feature.cashier.CartItem
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import retrofit2.Response

/**
 * Sprint 7 — local offline CASH sale storage. Verifies a client reference is
 * generated, item snapshots are stored, and the cart is cleared ONLY after a
 * successful local save.
 */
class OfflineSaleRepositoryTest {

    private fun repo(db: FakeOfflineDb, ref: () -> String = { "ref-fixed" }): OfflineSaleRepository =
        OfflineSaleRepository(
            offlineSaleDao = db,
            offlineSaleItemDao = db,
            api = FakeSyncApi(listOf(Response.success(SaleResponse(data = sampleSale())))),
            referenceProvider = ref,
            clock = { 1_000L },
        )

    @Test
    fun `createOfflineCashSale generates client reference and stores it`() = runTest {
        val db = FakeOfflineDb()
        val result = repo(db, ref = { "offline-uuid-xyz" }).createOfflineCashSale(
            items = listOf(CartItem(1L, "Kopi", 10000.0, 2)),
            paidAmount = 25000.0,
        )

        assertTrue(result is OfflineSaleRepository.SaveResult.Saved)
        result as OfflineSaleRepository.SaveResult.Saved
        assertEquals("offline-uuid-xyz", result.clientReference)

        val stored = db.sales.values.single()
        assertEquals("offline-uuid-xyz", stored.clientReference)
        assertEquals("PENDING", stored.syncStatus)
        assertEquals(20000.0, stored.grandTotal, 0.001)
        assertEquals(5000.0, stored.changeAmount, 0.001)
    }

    @Test
    fun `offline sale stores item snapshots`() = runTest {
        val db = FakeOfflineDb()
        repo(db).createOfflineCashSale(
            items = listOf(
                CartItem(1L, "Kopi", 10000.0, 2),
                CartItem(2L, "Teh", 8000.0, 1),
            ),
            paidAmount = 30000.0,
        )

        assertEquals(2, db.items.size)
        val kopi = db.items.first { it.productId == 1L }
        assertEquals("Kopi", kopi.productName)
        assertEquals(10000.0, kopi.unitPrice, 0.001)
        assertEquals(2, kopi.qty)
        assertEquals(20000.0, kopi.subtotal, 0.001)
        // Items belong to the stored sale.
        val sale = db.sales.values.single()
        assertTrue(db.items.all { it.offlineSaleLocalId == sale.localId })
    }

    @Test
    fun `empty cart is rejected and stores nothing`() = runTest {
        val db = FakeOfflineDb()
        val result = repo(db).createOfflineCashSale(emptyList(), paidAmount = 0.0)

        assertTrue(result is OfflineSaleRepository.SaveResult.Error)
        assertTrue(db.sales.isEmpty())
    }

    @Test
    fun `insufficient paid amount is rejected`() = runTest {
        val db = FakeOfflineDb()
        val result = repo(db).createOfflineCashSale(
            items = listOf(CartItem(1L, "Kopi", 10000.0, 1)),
            paidAmount = 5000.0,
        )

        assertTrue(result is OfflineSaleRepository.SaveResult.Error)
        assertTrue(db.sales.isEmpty())
    }

    @Test
    fun `cart is cleared only after a successful save`() = runTest {
        val db = FakeOfflineDb()
        val repository = repo(db)
        val cart = CartRepository().apply { addProduct(1L, "Kopi", 10000.0) }

        // Success path: caller clears the cart.
        val ok = repository.createOfflineCashSale(cart.items(), paidAmount = 10000.0)
        if (ok is OfflineSaleRepository.SaveResult.Saved) cart.clear()
        assertTrue(cart.isEmpty())

        // Failure path: an empty cart save fails, so a re-added cart is NOT cleared.
        cart.addProduct(2L, "Teh", 8000.0)
        val failed = repository.createOfflineCashSale(emptyList(), paidAmount = 0.0)
        if (failed is OfflineSaleRepository.SaveResult.Saved) cart.clear()
        assertFalse(cart.isEmpty())
    }
}
