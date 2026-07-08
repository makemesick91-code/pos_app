package com.aishtech.poslite

import com.aishtech.poslite.core.runtime.AndroidRuntimeMessages
import com.aishtech.poslite.core.runtime.AndroidRuntimePosture
import com.aishtech.poslite.core.runtime.RuntimeStatus
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Sprint 34 — the POS client maps the server runtime posture to write-allowed /
 * read-only + a friendly message, and fails SAFE when the snapshot is stale
 * (ADR-R007/R008/R009/R025). Enforcement stays server-side.
 */
class AndroidRuntimeStateTest {

    @Test
    fun `allowed posture permits writes`() {
        val posture = AndroidRuntimePosture(RuntimeStatus.ALLOWED, "ALLOWED_ACTIVE_PAID")
        assertTrue(posture.writeAllowed)
        assertFalse(posture.readOnly)
    }

    @Test
    fun `blocked and read only postures deny writes`() {
        assertFalse(AndroidRuntimePosture(RuntimeStatus.BLOCKED, "MANUALLY_SUSPENDED").writeAllowed)
        assertTrue(AndroidRuntimePosture(RuntimeStatus.READ_ONLY, "TRIAL_EXPIRED").readOnly)
    }

    @Test
    fun `stale snapshot fails safe to read only`() {
        val posture = AndroidRuntimePosture(RuntimeStatus.ALLOWED, "ALLOWED_ACTIVE_PAID", stale = true)
        assertFalse(posture.writeAllowed)
        assertTrue(AndroidRuntimePosture.failSafe().readOnly)
    }

    @Test
    fun `reason codes map to friendly messages`() {
        assertNotNull(AndroidRuntimeMessages.messageFor("MANUALLY_SUSPENDED"))
        assertNotNull(AndroidRuntimeMessages.messageFor("UNPAID_PAST_GRACE"))
        assertNotNull(AndroidRuntimeMessages.messageFor("TRIAL_EXPIRED"))
        assertNotNull(AndroidRuntimeMessages.messageFor("DEVICE_REVOKED"))
        assertNull(AndroidRuntimeMessages.messageFor("ALLOWED_ACTIVE_PAID"))
    }

    @Test
    fun `wire status parsing is lenient`() {
        assertEquals(RuntimeStatus.READ_ONLY, RuntimeStatus.fromWire("read_only"))
        assertEquals(RuntimeStatus.UNKNOWN, RuntimeStatus.fromWire("nonsense"))
        assertEquals(RuntimeStatus.UNKNOWN, RuntimeStatus.fromWire(null))
    }
}
