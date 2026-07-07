package com.aishtech.poslite.feature.subscription

import com.aishtech.poslite.data.remote.dto.SubscriptionStatusDto

/**
 * Pure mapping from the backend subscription status to a lightweight UI model
 * (Sprint 10). Kept free of Android types so it is unit-testable. The backend
 * `allowed` flag is authoritative — this never upgrades a blocked status to
 * allowed.
 */
object SubscriptionStatusDisplay {

    data class UiModel(
        val allowed: Boolean,
        val statusLabel: String,
        val planLabel: String,
        val deviceLabel: String,
        val reason: String?,
    )

    fun map(dto: SubscriptionStatusDto): UiModel {
        return UiModel(
            allowed = dto.allowed,
            statusLabel = statusLabel(dto),
            planLabel = planLabel(dto),
            deviceLabel = deviceLabel(dto),
            reason = if (dto.allowed) null else blockedReason(dto),
        )
    }

    fun isAllowed(dto: SubscriptionStatusDto): Boolean = dto.allowed

    /** True when the active device count has reached the plan's device cap. */
    fun isDeviceLimitReached(dto: SubscriptionStatusDto): Boolean {
        val devices = dto.devices ?: return false
        val max = devices.maxDevices ?: return false
        val active = devices.activeCount ?: return false
        return active >= max
    }

    fun statusLabel(dto: SubscriptionStatusDto): String =
        (dto.status ?: "UNKNOWN").uppercase()

    fun planLabel(dto: SubscriptionStatusDto): String {
        val plan = dto.plan ?: return "Paket: -"
        val name = plan.name ?: plan.code ?: "-"
        return "Paket: $name"
    }

    fun deviceLabel(dto: SubscriptionStatusDto): String {
        val devices = dto.devices
        val active = devices?.activeCount ?: 0
        val max = devices?.maxDevices
        return if (max != null) "Perangkat: $active / $max" else "Perangkat: $active"
    }

    /** A user-friendly blocked reason for the status/registration flows. */
    fun blockedReason(dto: SubscriptionStatusDto): String {
        if (!dto.reason.isNullOrBlank()) return dto.reason
        return when (dto.status?.uppercase()) {
            "EXPIRED" -> "Langganan telah berakhir."
            "CANCELLED" -> "Langganan dibatalkan."
            "SUSPENDED" -> "Langganan ditangguhkan."
            else -> "Langganan tidak aktif."
        }
    }

    /** Blocked message specifically for the device-limit case. */
    fun deviceLimitMessage(dto: SubscriptionStatusDto): String {
        val max = dto.devices?.maxDevices ?: return "Batas perangkat tercapai."
        return "Batas perangkat tercapai (maksimal $max)."
    }
}
