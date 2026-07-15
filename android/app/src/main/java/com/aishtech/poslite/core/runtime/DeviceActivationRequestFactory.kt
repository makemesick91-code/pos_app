package com.aishtech.poslite.core.runtime

import com.aishtech.poslite.data.remote.dto.ActivateDeviceRequestDto

/**
 * Sprint 34 — builds a device activation request from an activation code + the
 * local device fingerprint/uuid (ADR-R002/R003/R021).
 *
 * The raw activation token is used ONLY to build the wire request; it is never
 * logged. `redactedForLog()` guarantees no code path can accidentally emit the
 * token or the fingerprint into a log line.
 */
data class DeviceActivationInput(
    val activationToken: String,
    val deviceFingerprint: String,
    val deviceUuid: String,
    val deviceLabel: String? = null,
    val appVersion: String? = null,
    val installationId: String? = null,
) {
    /** A log-safe representation — the token and fingerprint are always redacted. */
    fun redactedForLog(): String =
        "DeviceActivationInput(token=[REDACTED], fingerprint=[REDACTED], deviceUuid=$deviceUuid, label=${deviceLabel ?: ""})"

    // Never let the default data-class toString leak the token/fingerprint.
    override fun toString(): String = redactedForLog()
}

object DeviceActivationRequestFactory {

    fun build(input: DeviceActivationInput): ActivateDeviceRequestDto =
        ActivateDeviceRequestDto(
            activationToken = input.activationToken,
            deviceFingerprint = input.deviceFingerprint,
            deviceUuid = input.deviceUuid,
            deviceLabel = input.deviceLabel,
            appVersion = input.appVersion,
            installationId = input.installationId,
        )
}
