package com.aishtech.poslite

import com.aishtech.poslite.core.session.CleanupOp
import com.aishtech.poslite.core.session.DataScope
import com.aishtech.poslite.core.session.LocalDataCleaner
import com.aishtech.poslite.core.session.ScopedStore
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-07 — classified cross-tenant cache hygiene (UIX8C-R224/R235/R236). A
 * cashier switch clears only cashier-scoped state; a tenant reset re-scopes
 * everything tenant-bound; GLOBAL and DEVICE scopes are NEVER cleared so the
 * device stays activated across logout/switch.
 */
class LocalDataCleanerTest {

    private fun cleanerWithAllScopes(): Pair<LocalDataCleaner, MutableSet<String>> {
        val cleared = mutableSetOf<String>()
        val stores = DataScope.entries.map { scope ->
            ScopedStore("store_${scope.name}", scope) { cleared += "store_${scope.name}" }
        }
        return LocalDataCleaner(stores) to cleared
    }

    @Test
    fun `logout clears only cashier scope`() = runTest {
        val (cleaner, cleared) = cleanerWithAllScopes()
        cleaner.clear(CleanupOp.LOGOUT)
        assertEquals(setOf("store_CASHIER"), cleared)
    }

    @Test
    fun `account switch clears only cashier scope`() = runTest {
        val (cleaner, cleared) = cleanerWithAllScopes()
        cleaner.clear(CleanupOp.ACCOUNT_SWITCH)
        assertEquals(setOf("store_CASHIER"), cleared)
    }

    @Test
    fun `tenant reset clears cashier outlet tenant and transaction but never global or device`() = runTest {
        val (cleaner, cleared) = cleanerWithAllScopes()
        cleaner.clear(CleanupOp.TENANT_RESET)
        assertTrue(cleared.containsAll(setOf("store_CASHIER", "store_OUTLET", "store_TENANT", "store_TRANSACTION")))
        assertFalse(cleared.contains("store_GLOBAL"))
        assertFalse(cleared.contains("store_DEVICE"))
    }

    @Test
    fun `device and global scopes survive every switch operation`() = runTest {
        for (op in listOf(CleanupOp.LOGOUT, CleanupOp.ACCOUNT_SWITCH, CleanupOp.OUTLET_SWITCH, CleanupOp.TENANT_RESET)) {
            val (cleaner, cleared) = cleanerWithAllScopes()
            cleaner.clear(op)
            assertFalse("$op cleared DEVICE", cleared.contains("store_DEVICE"))
            assertFalse("$op cleared GLOBAL", cleared.contains("store_GLOBAL"))
        }
    }

    @Test
    fun `scopesFor never includes GLOBAL or DEVICE`() {
        val cleaner = LocalDataCleaner(emptyList())
        for (op in CleanupOp.entries) {
            val scopes = cleaner.scopesFor(op)
            assertFalse(scopes.contains(DataScope.GLOBAL))
            assertFalse(scopes.contains(DataScope.DEVICE))
        }
    }
}
