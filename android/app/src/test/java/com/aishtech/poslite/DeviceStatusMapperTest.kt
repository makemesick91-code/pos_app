package com.aishtech.poslite

import com.aishtech.poslite.core.runtime.DeviceStatusMapper
import com.aishtech.poslite.core.runtime.DeviceTrust
import com.aishtech.poslite.data.remote.dto.DeviceStatusDto
import com.aishtech.poslite.data.remote.dto.DeviceStatusResponseDto
import com.aishtech.poslite.data.remote.dto.DeviceStatusTenantDto
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-07 — fail-closed device-status mapping (UIX8C-R214/R220/R221). ACTIVE is
 * derived only from an explicit server "active"; every other/absent/unknown value
 * is NOT active, and revoked is always honoured.
 */
class DeviceStatusMapperTest {

    private fun dto(status: String?, active: Boolean?, revoked: Boolean? = false, reason: String? = null) =
        DeviceStatusDto(
            status = status, active = active, revoked = revoked, revocationReason = reason,
            tenant = DeviceStatusTenantDto(1L, "Toko A"), outlet = null,
            deviceName = "Kasir 1", appVersion = "0.1.0", activatedAt = null, lastSeenAt = null,
        )

    @Test
    fun `null response maps to UNKNOWN and not active`() {
        val s = DeviceStatusMapper.map(null as DeviceStatusResponseDto?)
        assertEquals(DeviceTrust.UNKNOWN, s.trust)
        assertFalse(s.active)
        assertFalse(s.revoked)
    }

    @Test
    fun `explicit active maps to ACTIVE`() {
        val s = DeviceStatusMapper.map(dto("active", true))
        assertEquals(DeviceTrust.ACTIVE, s.trust)
        assertTrue(s.active)
    }

    @Test
    fun `revoked flag maps to REVOKED with reason and is never active`() {
        val s = DeviceStatusMapper.map(dto("revoked", false, revoked = true, reason = "hilang"))
        assertEquals(DeviceTrust.REVOKED, s.trust)
        assertTrue(s.revoked)
        assertFalse(s.active)
        assertEquals("hilang", s.revocationReason)
    }

    @Test
    fun `revoked wins even if active is somehow true`() {
        val s = DeviceStatusMapper.map(dto("active", true, revoked = true))
        assertEquals(DeviceTrust.REVOKED, s.trust)
        assertFalse(s.active)
    }

    @Test
    fun `not_activated and expired and inactive are distinct and not active`() {
        assertEquals(DeviceTrust.NOT_ACTIVATED, DeviceStatusMapper.map(dto("not_activated", false)).trust)
        assertEquals(DeviceTrust.EXPIRED, DeviceStatusMapper.map(dto("expired", false)).trust)
        assertEquals(DeviceTrust.INACTIVE, DeviceStatusMapper.map(dto("inactive", false)).trust)
    }

    @Test
    fun `unrecognised status maps to UNKNOWN (fail closed)`() {
        val s = DeviceStatusMapper.map(dto("banana", true))
        // status active-word missing -> not ACTIVE; unknown status word -> UNKNOWN
        assertEquals(DeviceTrust.UNKNOWN, s.trust)
        assertFalse(s.active)
    }

    @Test
    fun `blank revocation reason is normalised to null`() {
        val s = DeviceStatusMapper.map(dto("revoked", false, revoked = true, reason = "  "))
        assertNull(s.revocationReason)
    }
}
