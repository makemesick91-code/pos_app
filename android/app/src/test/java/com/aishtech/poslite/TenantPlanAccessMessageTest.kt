package com.aishtech.poslite

import com.aishtech.poslite.core.network.TenantPlanMessages
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Sprint 26 — the POS client maps a server-side FEATURE_NOT_ENTITLED (403) or
 * USAGE_LIMIT_EXCEEDED (429) response to a friendly message and never crashes.
 * Enforcement stays server-side; the client is UX only (TPE-R010).
 */
class TenantPlanAccessMessageTest {

    @Test
    fun `feature not entitled maps to friendly message`() {
        val message = TenantPlanMessages.messageFor(403, "FEATURE_NOT_ENTITLED")
        assertNotNull(message)
        assertTrue(TenantPlanMessages.isFeatureNotEntitled("FEATURE_NOT_ENTITLED"))
        assertTrue(TenantPlanMessages.isPlanBlock(403, "FEATURE_NOT_ENTITLED"))
    }

    @Test
    fun `usage limit exceeded maps to friendly message`() {
        val message = TenantPlanMessages.messageFor(429, "USAGE_LIMIT_EXCEEDED")
        assertNotNull(message)
        assertTrue(TenantPlanMessages.isUsageLimitExceeded(429, "USAGE_LIMIT_EXCEEDED"))
        assertTrue(TenantPlanMessages.isUsageLimitExceeded(429, null))
    }

    @Test
    fun `non plan responses return no message`() {
        assertNull(TenantPlanMessages.messageFor(200, null))
        assertNull(TenantPlanMessages.messageFor(423, "TENANT_SUSPENDED"))
        assertFalse(TenantPlanMessages.isPlanBlock(500, "SERVER_ERROR"))
    }

    @Test
    fun `messages are non-empty and user friendly`() {
        assertEquals(false, TenantPlanMessages.messageFor(403, "FEATURE_NOT_ENTITLED").isNullOrBlank())
        assertEquals(false, TenantPlanMessages.messageFor(429, "USAGE_LIMIT_EXCEEDED").isNullOrBlank())
    }

    @Test
    fun `suspended lifecycle code is not treated as a plan block`() {
        // Lifecycle suspension (Sprint 25) is a separate, higher-precedence gate.
        assertFalse(TenantPlanMessages.isFeatureNotEntitled("TENANT_SUSPENDED"))
        assertNull(TenantPlanMessages.messageFor(423, "TENANT_SUSPENDED"))
    }
}
