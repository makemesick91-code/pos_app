package com.aishtech.poslite.feature.cashier

import com.aishtech.poslite.data.remote.dto.MeResponse

/**
 * Presentation model for the cashier home context header (UIX8C-R061/R062): the
 * canonical business / outlet / cashier / device identity plus a truthful
 * connectivity signal. Names come only from the authenticated `auth/me` response
 * — never from client-supplied input (UIX8C-R063). A missing value renders
 * [CashierContextPresenter.UNAVAILABLE] ("Tidak tersedia"), never a fabricated
 * blank or placeholder that looks authoritative (UIX8C-R024 lineage).
 */
data class CashierContext(
    val businessName: String,
    val outletName: String,
    val cashierName: String,
    val roleLabel: String,
    val deviceName: String,
    /** Governed reachability, not a raw radio flag: true only when [MeResponse]
     *  was resolved from the server this session ("online ≠ merely connected"). */
    val online: Boolean,
) {
    /** Long names must ellipsize safely, so the header pre-formats a compact,
     *  single-line cashier label ("Name · Role") the layout truncates on. */
    val cashierLine: String
        get() = if (roleLabel.isBlank()) cashierName else "$cashierName · $roleLabel"
}

/**
 * Pure (framework-free) mapper so the truthful-context logic is unit-testable on
 * the JVM without Android or the network stack.
 */
object CashierContextPresenter {

    const val UNAVAILABLE = "Tidak tersedia"

    /**
     * @param me         canonical identity, or null when it could not be resolved.
     * @param deviceName local device display name (always available on-device).
     * @param reachable  whether [me] was resolved from the server this session.
     */
    fun present(me: MeResponse?, deviceName: String, reachable: Boolean): CashierContext =
        CashierContext(
            businessName = me?.tenant?.name.orUnavailable(),
            outletName = me?.store?.name.orUnavailable(),
            cashierName = me?.user?.name.orUnavailable(),
            roleLabel = me?.user?.role?.takeUnless { it.isBlank() }?.let { roleLabel(it) }.orEmpty(),
            deviceName = deviceName.takeUnless { it.isBlank() } ?: UNAVAILABLE,
            online = reachable && me != null,
        )

    private fun String?.orUnavailable(): String =
        this?.trim()?.takeUnless { it.isEmpty() } ?: UNAVAILABLE

    private fun roleLabel(role: String): String = when (role.trim().lowercase()) {
        "cashier" -> "Kasir"
        "tenant_owner", "owner" -> "Pemilik"
        "manager" -> "Manajer"
        else -> role.trim()
    }
}
