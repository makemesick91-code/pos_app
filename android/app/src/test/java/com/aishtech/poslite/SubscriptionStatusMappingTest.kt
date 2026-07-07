package com.aishtech.poslite

import com.aishtech.poslite.data.remote.dto.DeviceLimitDto
import com.aishtech.poslite.data.remote.dto.SubscriptionPlanDto
import com.aishtech.poslite.data.remote.dto.SubscriptionStatusDto
import com.aishtech.poslite.feature.subscription.SubscriptionStatusDisplay
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Sprint 10 — the pure mapping from a backend subscription status to the UI
 * model never upgrades a blocked status to allowed and produces a clear blocked
 * reason (including the device-limit case).
 */
class SubscriptionStatusMappingTest {

    private fun dto(
        allowed: Boolean,
        status: String,
        reason: String? = null,
        active: Int = 1,
        max: Int? = 3,
    ) = SubscriptionStatusDto(
        allowed = allowed,
        status = status,
        reason = reason,
        plan = SubscriptionPlanDto(code = "starter", name = "Starter", maxDevices = max, maxStores = 1, maxProducts = null),
        devices = DeviceLimitDto(activeCount = active, maxDevices = max),
    )

    @Test
    fun `active status maps to an allowed ui model`() {
        val model = SubscriptionStatusDisplay.map(dto(allowed = true, status = "ACTIVE"))

        assertTrue(model.allowed)
        assertEquals("ACTIVE", model.statusLabel)
        assertEquals("Paket: Starter", model.planLabel)
        assertEquals("Perangkat: 1 / 3", model.deviceLabel)
        assertNull(model.reason)
    }

    @Test
    fun `expired cancelled suspended map to blocked ui models`() {
        for (status in listOf("EXPIRED", "CANCELLED", "SUSPENDED")) {
            val model = SubscriptionStatusDisplay.map(dto(allowed = false, status = status))
            assertFalse("$status should be blocked", model.allowed)
            assertNotNull("$status should carry a reason", model.reason)
        }
    }

    @Test
    fun `device limit reached is detected and produces a blocked message`() {
        val atLimit = dto(allowed = true, status = "ACTIVE", active = 3, max = 3)

        assertTrue(SubscriptionStatusDisplay.isDeviceLimitReached(atLimit))
        assertTrue(SubscriptionStatusDisplay.deviceLimitMessage(atLimit).contains("3"))

        val underLimit = dto(allowed = true, status = "ACTIVE", active = 1, max = 3)
        assertFalse(SubscriptionStatusDisplay.isDeviceLimitReached(underLimit))
    }

    @Test
    fun `backend reason is preferred when present`() {
        val model = SubscriptionStatusDisplay.map(
            dto(allowed = false, status = "EXPIRED", reason = "Subscription has expired."),
        )
        assertEquals("Subscription has expired.", model.reason)
    }
}
