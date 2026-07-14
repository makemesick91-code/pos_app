package com.aishtech.poslite

import com.aishtech.poslite.core.util.Event
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8B — the one-time event contract (UIX8B-R008): content is delivered exactly
 * once; a second read (e.g. after rotation re-observing the sticky value) returns
 * null so navigation/toasts never replay.
 */
class EventTest {

    @Test
    fun contentIsDeliveredExactlyOnce() {
        val event = Event("go")
        assertFalse(event.hasBeenHandled)
        assertEquals("go", event.getContentIfNotHandled())
        assertTrue(event.hasBeenHandled)
        assertNull(event.getContentIfNotHandled())
    }

    @Test
    fun peekDoesNotConsume() {
        val event = Event(7)
        assertEquals(7, event.peekContent())
        assertEquals(7, event.getContentIfNotHandled())
    }
}
