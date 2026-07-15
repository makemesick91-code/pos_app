package com.aishtech.poslite.core.runtime

import com.aishtech.poslite.data.remote.dto.DeviceStatusDto
import com.aishtech.poslite.data.remote.dto.DeviceStatusResponseDto

/**
 * UIX-8C-07 — the client-side, fail-closed view of the server-authoritative
 * device status (UIX8C-R221). The device is trusted as ACTIVE only when the
 * server explicitly says so; every other value (revoked, expired, not-activated,
 * inactive, or an unparseable/absent response) is treated as NOT active, and a
 * revoked device is always reported revoked so the startup state machine fails
 * closed (UIX8C-R220).
 */
enum class DeviceTrust {
    ACTIVE,
    REVOKED,
    EXPIRED,
    NOT_ACTIVATED,
    INACTIVE,
    UNKNOWN,
}

data class DeviceStatus(
    val trust: DeviceTrust,
    val revocationReason: String?,
    val tenantId: Long?,
    val tenantName: String?,
    val outletId: Long?,
    val outletName: String?,
    val deviceName: String?,
    val appVersion: String?,
    val activatedAt: String?,
    val lastSeenAt: String?,
) {
    /** The single truth the state machine uses; ACTIVE only. */
    val active: Boolean get() = trust == DeviceTrust.ACTIVE

    /** A revoked device must fail closed regardless of any local belief. */
    val revoked: Boolean get() = trust == DeviceTrust.REVOKED

    companion object {
        /** Used before the first successful poll or on a transport/parse failure. */
        fun unknown(): DeviceStatus = DeviceStatus(
            trust = DeviceTrust.UNKNOWN,
            revocationReason = null,
            tenantId = null, tenantName = null,
            outletId = null, outletName = null,
            deviceName = null, appVersion = null,
            activatedAt = null, lastSeenAt = null,
        )
    }
}

object DeviceStatusMapper {

    /**
     * Map a server response to a fail-closed [DeviceStatus]. A null body, an
     * unrecognised status, or `revoked=true` never yields ACTIVE.
     */
    fun map(response: DeviceStatusResponseDto?): DeviceStatus {
        val dto = response?.data ?: return DeviceStatus.unknown()
        return map(dto)
    }

    fun map(dto: DeviceStatusDto): DeviceStatus {
        val trust = when {
            dto.revoked == true || dto.status.equalsIgnoreCase("revoked") -> DeviceTrust.REVOKED
            dto.status.equalsIgnoreCase("expired") -> DeviceTrust.EXPIRED
            dto.status.equalsIgnoreCase("not_activated") -> DeviceTrust.NOT_ACTIVATED
            dto.active == true && dto.status.equalsIgnoreCase("active") -> DeviceTrust.ACTIVE
            dto.status.equalsIgnoreCase("inactive") -> DeviceTrust.INACTIVE
            else -> DeviceTrust.UNKNOWN
        }
        return DeviceStatus(
            trust = trust,
            revocationReason = dto.revocationReason?.takeUnless { it.isBlank() },
            tenantId = dto.tenant?.id,
            tenantName = dto.tenant?.name,
            outletId = dto.outlet?.id,
            outletName = dto.outlet?.name,
            deviceName = dto.deviceName,
            appVersion = dto.appVersion,
            activatedAt = dto.activatedAt,
            lastSeenAt = dto.lastSeenAt,
        )
    }

    private fun String?.equalsIgnoreCase(other: String): Boolean =
        this != null && this.equals(other, ignoreCase = true)
}
