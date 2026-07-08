package com.aishtech.poslite

import com.aishtech.poslite.core.network.TenantAccessMessages
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Sprint 25 — the POS client maps a server-side TENANT_SUSPENDED / 423 response
 * to a friendly message and never crashes. Enforcement stays server-side; the
 * client is UX only (TLS-R009).
 */
class TenantSuspensionMessageTest {

    @Test
    fun `423 locked maps to friendly suspension message`() {
        val message = TenantAccessMessages.messageFor(TenantAccessMessages.HTTP_LOCKED, "TENANT_SUSPENDED")
        assertNotNull(message)
        assertTrue(TenantAccessMessages.isTenantSuspended(423, "TENANT_SUSPENDED"))
    }

    @Test
    fun `tenant suspended code without 423 still recognized`() {
        assertTrue(TenantAccessMessages.isTenantSuspended(403, "TENANT_SUSPENDED"))
        assertTrue(TenantAccessMessages.isTenantSuspended(423, null))
    }

    @Test
    fun `archived tenant is treated as suspended for UX`() {
        assertTrue(TenantAccessMessages.isTenantSuspended(423, "TENANT_ARCHIVED"))
    }

    @Test
    fun `non-suspension responses return no message`() {
        assertNull(TenantAccessMessages.messageFor(200, null))
        assertNull(TenantAccessMessages.messageFor(402, "SUBSCRIPTION_INACTIVE"))
        assertFalse(TenantAccessMessages.isTenantSuspended(500, "SERVER_ERROR"))
    }

    @Test
    fun `message is non-empty and user friendly`() {
        val message = TenantAccessMessages.messageFor(423, "TENANT_SUSPENDED")
        assertEquals(false, message.isNullOrBlank())
    }
}
