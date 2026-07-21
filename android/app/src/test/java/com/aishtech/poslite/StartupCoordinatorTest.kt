package com.aishtech.poslite

import com.aishtech.poslite.core.startup.BootState
import com.aishtech.poslite.core.startup.StartupCoordinator
import com.aishtech.poslite.core.startup.StartupInputs
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-07 — the deterministic startup/auth state machine (UIX8C-R211/R212/R213).
 * The evaluation order encodes the security precedence: fatal → db → activation →
 * device revoked/invalid → tenant consistency → session → cashier authorization →
 * Ready/OfflineReady.
 */
class StartupCoordinatorTest {

    private val coordinator = StartupCoordinator()

    /** A fully-valid, online, ready device. */
    private fun ready() = StartupInputs(
        dbReady = true,
        activationPresent = true,
        deviceStatusReached = true,
        deviceRevoked = false,
        revocationReason = null,
        deviceActive = true,
        hasStoredSession = true,
        sessionValid = true,
        sessionExpired = false,
        tenantConsistent = true,
        cashierAuthorized = true,
        pendingUnsynced = 0,
    )

    @Test
    fun `all gates valid online yields Ready`() {
        assertEquals(BootState.Ready, coordinator.evaluate(ready()))
    }

    @Test
    fun `fatal wins over everything`() {
        val r = coordinator.evaluate(ready().copy(fatal = true, fatalMessage = "boom"))
        assertTrue(r is BootState.FatalFailure)
    }

    @Test
    fun `db not ready routes to DatabaseMigration`() {
        assertEquals(BootState.DatabaseMigration, coordinator.evaluate(ready().copy(dbReady = false)))
    }

    @Test
    fun `no activation routes to ActivationRequired before any session work`() {
        val r = coordinator.evaluate(ready().copy(activationPresent = false, hasStoredSession = false))
        assertEquals(BootState.ActivationRequired, r)
    }

    @Test
    fun `revoked device fails closed even with a valid session`() {
        val r = coordinator.evaluate(ready().copy(deviceRevoked = true, revocationReason = "hilang"))
        assertTrue(r is BootState.DeviceRevoked)
        assertEquals("hilang", (r as BootState.DeviceRevoked).reason)
    }

    @Test
    fun `reached server reporting inactive device is DeviceInvalid`() {
        val r = coordinator.evaluate(ready().copy(deviceActive = false))
        assertEquals(BootState.DeviceInvalid, r)
    }

    @Test
    fun `revocation is only trusted when the status poll was reached (connectivity is not validity)`() {
        // Offline: status not reached, so a stale local 'revoked' guess is ignored.
        val r = coordinator.evaluate(
            ready().copy(deviceStatusReached = false, deviceRevoked = true, deviceActive = false, sessionValid = false),
        )
        // Not DeviceRevoked/Invalid — falls through to offline continuation.
        assertEquals(BootState.OfflineReady, r)
    }

    @Test
    fun `tenant mismatch fails closed to ContextMismatch`() {
        assertEquals(BootState.ContextMismatch, coordinator.evaluate(ready().copy(tenantConsistent = false)))
    }

    @Test
    fun `no stored session routes to LoginRequired`() {
        val r = coordinator.evaluate(ready().copy(hasStoredSession = false, sessionValid = false))
        assertEquals(BootState.LoginRequired, r)
    }

    @Test
    fun `expired session routes to SessionExpired and never drops pending`() {
        val r = coordinator.evaluate(ready().copy(sessionValid = false, sessionExpired = true, pendingUnsynced = 3))
        assertEquals(BootState.SessionExpired, r)
    }

    @Test
    fun `valid session but unauthorized cashier is ContextMismatch`() {
        assertEquals(BootState.ContextMismatch, coordinator.evaluate(ready().copy(cashierAuthorized = false)))
    }

    @Test
    fun `stored session with unreachable server yields OfflineReady`() {
        val r = coordinator.evaluate(
            ready().copy(deviceStatusReached = false, sessionValid = false, sessionExpired = false),
        )
        assertEquals(BootState.OfflineReady, r)
    }

    // ---- UIX-8C-08 (DEF-006): a confirmed revocation survives going offline ----
    // Found on physical hardware: a revoked device regained the full cashier surface
    // by enabling airplane mode and restarting, because revocation was only enforced
    // when the status poll answered. UIX8C-R220 forbids bypass via offline mode.

    @Test
    fun `known revoked device stays fail-closed when the status poll is unreachable`() {
        val r = coordinator.evaluate(
            ready().copy(
                deviceStatusReached = false,
                deviceRevoked = false,
                sessionValid = false,
                sessionExpired = false,
                deviceRevokedKnownLocally = true,
                knownRevocationReason = "Perangkat dicabut",
            ),
        )
        assertEquals(BootState.DeviceRevoked("Perangkat dicabut"), r)
    }

    @Test
    fun `known revoked device is never downgraded to OfflineReady`() {
        val r = coordinator.evaluate(
            ready().copy(deviceStatusReached = false, sessionValid = false, deviceRevokedKnownLocally = true),
        )
        assertNotEquals(BootState.OfflineReady, r)
        assertTrue(r is BootState.DeviceRevoked)
    }

    @Test
    fun `known revoked device outranks a still-valid stored session`() {
        val r = coordinator.evaluate(
            ready().copy(deviceStatusReached = false, sessionValid = true, deviceRevokedKnownLocally = true),
        )
        assertTrue(r is BootState.DeviceRevoked)
    }

    @Test
    fun `without a cached revocation the offline path is unchanged`() {
        val r = coordinator.evaluate(
            ready().copy(deviceStatusReached = false, sessionValid = false, deviceRevokedKnownLocally = false),
        )
        assertEquals(BootState.OfflineReady, r)
    }
}
