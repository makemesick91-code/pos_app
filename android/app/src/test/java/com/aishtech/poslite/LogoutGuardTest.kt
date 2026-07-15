package com.aishtech.poslite

import com.aishtech.poslite.core.session.LogoutDecision
import com.aishtech.poslite.core.session.LogoutGuard
import com.aishtech.poslite.core.session.UnsyncedCounter
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-07 — the unsynced-transaction protection gate (UIX8C-R229/R230/R231).
 * Logout/switch is blocked whenever ANY un-acknowledged transaction exists —
 * PENDING or bounded-retry FAILED — so revenue is never silently discarded.
 */
class LogoutGuardTest {

    private class FakeCounter(val pending: Int, val failed: Int) : UnsyncedCounter {
        override suspend fun pendingCount(): Int = pending
        override suspend fun failedCount(): Int = failed
    }

    @Test
    fun `zero pending and failed allows logout`() = runTest {
        val decision = LogoutGuard(FakeCounter(0, 0)).evaluate()
        assertEquals(LogoutDecision.Allowed, decision)
    }

    @Test
    fun `one pending blocks logout`() = runTest {
        val decision = LogoutGuard(FakeCounter(1, 0)).evaluate()
        assertTrue(decision is LogoutDecision.BlockedByUnsynced)
        assertEquals(1, (decision as LogoutDecision.BlockedByUnsynced).total)
    }

    @Test
    fun `failed poison rows also block logout (not just visible pending)`() = runTest {
        val decision = LogoutGuard(FakeCounter(0, 2)).evaluate()
        assertTrue(decision is LogoutDecision.BlockedByUnsynced)
        val blocked = decision as LogoutDecision.BlockedByUnsynced
        assertEquals(0, blocked.pending)
        assertEquals(2, blocked.failed)
        assertEquals(2, blocked.total)
    }

    @Test
    fun `multiple pending and failed sum into the block total`() = runTest {
        val decision = LogoutGuard(FakeCounter(3, 2)).evaluate()
        assertEquals(5, (decision as LogoutDecision.BlockedByUnsynced).total)
    }
}
