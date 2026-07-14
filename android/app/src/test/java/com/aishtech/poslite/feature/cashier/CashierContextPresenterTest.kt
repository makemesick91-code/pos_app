package com.aishtech.poslite.feature.cashier

import com.aishtech.poslite.data.remote.dto.MeResponse
import com.aishtech.poslite.data.remote.dto.StoreDto
import com.aishtech.poslite.data.remote.dto.TenantDto
import com.aishtech.poslite.data.remote.dto.UserDto
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-03 — the cashier context header presents ONLY canonical auth/me identity
 * (UIX8C-R062/R063). A missing value renders "Tidak tersedia" and never a
 * fabricated blank, and the online chip is truthful (UIX8C-R047 lineage): online
 * only when identity resolved from the server this session.
 */
class CashierContextPresenterTest {

    private fun me(
        user: UserDto? = UserDto(1, "Siti Kasir", "siti@toko.id", "cashier", 9, 3),
        tenant: TenantDto? = TenantDto(9, "Toko Berkah", "active"),
        store: StoreDto? = StoreDto(3, "Cabang Pusat", "PST"),
    ) = MeResponse(user = user, tenant = tenant, store = store, foundation = null)

    @Test
    fun fullContextResolvesCanonicalNamesAndOnline() {
        val c = CashierContextPresenter.present(me(), deviceName = "Kasir-01", reachable = true)
        assertEquals("Toko Berkah", c.businessName)
        assertEquals("Cabang Pusat", c.outletName)
        assertEquals("Siti Kasir", c.cashierName)
        assertEquals("Kasir", c.roleLabel)
        assertEquals("Siti Kasir · Kasir", c.cashierLine)
        assertEquals("Kasir-01", c.deviceName)
        assertTrue(c.online)
    }

    @Test
    fun nullIdentityIsUnavailableAndOffline() {
        val c = CashierContextPresenter.present(me = null, deviceName = "Kasir-01", reachable = true)
        assertEquals(CashierContextPresenter.UNAVAILABLE, c.businessName)
        assertEquals(CashierContextPresenter.UNAVAILABLE, c.outletName)
        assertEquals(CashierContextPresenter.UNAVAILABLE, c.cashierName)
        // Device is a local fact and stays available even offline.
        assertEquals("Kasir-01", c.deviceName)
        // Online is false when identity could not be resolved, regardless of the
        // reachability flag (UIX8C-R062: never claim online without canonical proof).
        assertFalse(c.online)
    }

    @Test
    fun partialIdentityFillsMissingFieldsWithUnavailable() {
        val partial = me(store = null, tenant = TenantDto(9, "  ", null), user = UserDto(1, null, null, "", null, null))
        val c = CashierContextPresenter.present(partial, deviceName = "", reachable = true)
        assertEquals(CashierContextPresenter.UNAVAILABLE, c.businessName) // blank tenant name
        assertEquals(CashierContextPresenter.UNAVAILABLE, c.outletName)   // null store
        assertEquals(CashierContextPresenter.UNAVAILABLE, c.cashierName)  // null user name
        assertEquals(CashierContextPresenter.UNAVAILABLE, c.deviceName)   // blank device name
        assertEquals("", c.roleLabel)                                     // blank role omitted
        assertEquals(c.cashierName, c.cashierLine)                        // no " · role" appended
    }

    @Test
    fun reachableFalseIsOffline() {
        val c = CashierContextPresenter.present(me(), deviceName = "Kasir-01", reachable = false)
        assertFalse(c.online)
    }

    @Test
    fun ownerRoleIsLocalised() {
        val c = CashierContextPresenter.present(
            me(user = UserDto(1, "Budi", "b@x.id", "tenant_owner", 9, 3)),
            deviceName = "d", reachable = true,
        )
        assertEquals("Pemilik", c.roleLabel)
    }
}
