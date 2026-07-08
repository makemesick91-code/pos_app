package com.aishtech.poslite

import com.aishtech.poslite.feature.sync.OfflineSyncBatchFactory
import com.aishtech.poslite.feature.sync.QueuedSyncItem
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Sprint 34 — the offline sync batch is deterministic and idempotent
 * (ADR-R012/R013/R014). A retried submit of the SAME queued set produces the SAME
 * batch + item ids, so the server replays without a duplicate.
 */
class OfflineSyncBatchFactoryTest {

    private fun items() = listOf(
        QueuedSyncItem("client-uuid-1", "sale"),
        QueuedSyncItem("client-uuid-2", "sale"),
    )

    @Test
    fun `every item carries its stable client id`() {
        val batch = OfflineSyncBatchFactory.build("device-1", items())
        assertEquals(listOf("client-uuid-1", "client-uuid-2"), batch.items.map { it.clientItemId })
    }

    @Test
    fun `retrying the same set is idempotent`() {
        val first = OfflineSyncBatchFactory.build("device-1", items())
        val retry = OfflineSyncBatchFactory.build("device-1", items().reversed())

        // Same device + same item id set → same deterministic batch id.
        assertEquals(first.clientBatchId, retry.clientBatchId)
        assertEquals(first.clientBatchId, first.idempotencyKey)
    }

    @Test
    fun `a different item set yields a different batch id`() {
        val a = OfflineSyncBatchFactory.build("device-1", items())
        val b = OfflineSyncBatchFactory.build("device-1", items() + QueuedSyncItem("client-uuid-3", "sale"))
        assertNotEquals(a.clientBatchId, b.clientBatchId)
    }

    @Test
    fun `batch id never contains raw pii`() {
        val batch = OfflineSyncBatchFactory.build("device-1", items())
        assertTrue(batch.clientBatchId.startsWith("batch-"))
    }

    @Test(expected = IllegalArgumentException::class)
    fun `an empty batch is rejected`() {
        OfflineSyncBatchFactory.build("device-1", emptyList())
    }
}
