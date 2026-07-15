package com.aishtech.poslite

import com.aishtech.poslite.core.session.CleanupOp
import com.aishtech.poslite.core.session.DataScope
import com.aishtech.poslite.core.session.LocalDataCleaner
import com.aishtech.poslite.core.session.ScopedStore
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

/**
 * UIX-8C-07 — automated tenant-isolation proof (UIX8C-R228). Tenant A writes
 * tenant/outlet/cashier/transaction-scoped artifacts; a valid tenant reset clears
 * exactly those scopes; Tenant B then cannot read any Tenant A artifact — while
 * device/global-scoped data (device activation) survives.
 */
class CrossTenantCleanupTest {

    /** A trivial local KV that stands in for a tenant-scoped store. */
    private class Store {
        val data = mutableMapOf<String, String>()
    }

    @Test
    fun `tenant reset erases every tenant-scoped artifact so tenant B cannot read tenant A`() = runTest {
        val tenantCatalog = Store()
        val outletCursor = Store()
        val cashierCache = Store()
        val offlineDraft = Store()   // transaction-scoped, cleared only after guard proves 0 unsynced
        val deviceActivation = Store()
        val appConfig = Store()

        // Tenant A writes across every scope.
        tenantCatalog.data["product:1"] = "Kopi A"
        outletCursor.data["cursor"] = "2026-07-15"
        cashierCache.data["last_screen"] = "cart"
        offlineDraft.data["draft"] = "sale-A"
        deviceActivation.data["activated"] = "true"
        appConfig.data["theme"] = "light"

        val cleaner = LocalDataCleaner(
            listOf(
                ScopedStore("catalog", DataScope.TENANT) { tenantCatalog.data.clear() },
                ScopedStore("outlet", DataScope.OUTLET) { outletCursor.data.clear() },
                ScopedStore("cashier", DataScope.CASHIER) { cashierCache.data.clear() },
                ScopedStore("offline", DataScope.TRANSACTION) { offlineDraft.data.clear() },
                ScopedStore("device", DataScope.DEVICE) { deviceActivation.data.clear() },
                ScopedStore("global", DataScope.GLOBAL) { appConfig.data.clear() },
            ),
        )

        cleaner.clear(CleanupOp.TENANT_RESET)

        // Tenant B sees NONE of Tenant A's tenant-bound artifacts.
        assertNull(tenantCatalog.data["product:1"])
        assertNull(outletCursor.data["cursor"])
        assertNull(cashierCache.data["last_screen"])
        assertNull(offlineDraft.data["draft"])
        assertEquals(0, tenantCatalog.data.size)

        // Device activation + global config survive (device stays activated).
        assertEquals("true", deviceActivation.data["activated"])
        assertEquals("light", appConfig.data["theme"])
    }

    @Test
    fun `a cashier switch keeps the tenant catalog (same tenant) but clears cashier state`() = runTest {
        val tenantCatalog = Store().apply { data["product:1"] = "Kopi A" }
        val cashierCache = Store().apply { data["last_screen"] = "cart" }

        val cleaner = LocalDataCleaner(
            listOf(
                ScopedStore("catalog", DataScope.TENANT) { tenantCatalog.data.clear() },
                ScopedStore("cashier", DataScope.CASHIER) { cashierCache.data.clear() },
            ),
        )

        cleaner.clear(CleanupOp.ACCOUNT_SWITCH)

        assertEquals("Kopi A", tenantCatalog.data["product:1"]) // same tenant → kept
        assertNull(cashierCache.data["last_screen"])            // cashier state cleared
    }
}
