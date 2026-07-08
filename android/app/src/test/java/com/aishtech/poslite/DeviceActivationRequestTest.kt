package com.aishtech.poslite

import com.aishtech.poslite.core.runtime.DeviceActivationInput
import com.aishtech.poslite.core.runtime.DeviceActivationRequestFactory
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Sprint 34 — the activation request carries the raw token to the wire but never
 * to a log (ADR-R003/R021). toString()/redactedForLog() must not leak the token
 * or fingerprint.
 */
class DeviceActivationRequestTest {

    private val input = DeviceActivationInput(
        activationToken = "super-secret-activation-token",
        deviceFingerprint = "raw-hardware-fingerprint",
        deviceUuid = "device-uuid-1",
        deviceLabel = "Kasir Depan",
    )

    @Test
    fun `request dto carries the token for the wire`() {
        val dto = DeviceActivationRequestFactory.build(input)
        assertEquals("super-secret-activation-token", dto.activationToken)
        assertEquals("raw-hardware-fingerprint", dto.deviceFingerprint)
        assertEquals("device-uuid-1", dto.deviceUuid)
    }

    @Test
    fun `toString never leaks the token or fingerprint`() {
        val rendered = input.toString()
        assertFalse(rendered.contains("super-secret-activation-token"))
        assertFalse(rendered.contains("raw-hardware-fingerprint"))
        assertTrue(rendered.contains("[REDACTED]"))
        assertTrue(rendered.contains("device-uuid-1"))
    }

    @Test
    fun `redacted log form is safe`() {
        val log = input.redactedForLog()
        assertFalse(log.contains("super-secret-activation-token"))
        assertFalse(log.contains("raw-hardware-fingerprint"))
    }
}
