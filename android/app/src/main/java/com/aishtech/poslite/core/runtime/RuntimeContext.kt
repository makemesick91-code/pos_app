package com.aishtech.poslite.core.runtime

import com.aishtech.poslite.data.remote.dto.MeResponse

/**
 * UIX-8C-07 — the single runtime-context source of truth (UIX8C-R222). Every
 * repository, query, sync job, transaction write, receipt projection, printer
 * operation, and navigation decision derives its {tenant, outlet, cashier,
 * device, session, installation, appBuild} from here. Identity is server-derived
 * only (from `auth/me` + device status); it is never taken from client-supplied
 * UI input as authority (UIX8C-R223) and is validated before the app is `Ready`
 * (UIX8C-R225).
 */
data class RuntimeIdentity(
    val tenantId: Long,
    val tenantName: String,
    val outletId: Long?,
    val outletName: String?,
    val cashierId: Long,
    val cashierName: String,
    val roleLabel: String,
)

data class RuntimeContext(
    val identity: RuntimeIdentity,
    val deviceUuid: String,
    val deviceName: String?,
    val installationId: String,
    val appVersionName: String,
    val appVersionCode: Long,
    val buildType: String,
    val sessionValid: Boolean,
) {
    /** Two contexts belong to the same tenant/outlet (a cashier switch stays
     *  within this pair; a tenant/outlet change requires reactivation). */
    fun sameTenantOutlet(other: RuntimeContext): Boolean =
        identity.tenantId == other.identity.tenantId &&
            identity.outletId == other.identity.outletId

    fun matchesTenant(tenantId: Long?): Boolean =
        tenantId != null && identity.tenantId == tenantId

    companion object {
        /**
         * Build a validated context from server-derived identity. Returns null
         * when the canonical identity is incomplete (no tenant/cashier) — the
         * caller then routes to a recovery/login state rather than trusting a
         * partial context (UIX8C-R225).
         */
        fun fromServer(
            me: MeResponse?,
            deviceUuid: String,
            deviceName: String?,
            installationId: String,
            appVersionName: String,
            appVersionCode: Long,
            buildType: String,
            sessionValid: Boolean,
        ): RuntimeContext? {
            val tenantId = me?.tenant?.id ?: return null
            val cashierId = me.user?.id ?: return null
            val tenantName = me.tenant?.name?.takeUnless { it.isBlank() } ?: return null
            val cashierName = me.user?.name?.takeUnless { it.isBlank() } ?: return null
            return RuntimeContext(
                identity = RuntimeIdentity(
                    tenantId = tenantId,
                    tenantName = tenantName,
                    outletId = me.store?.id,
                    outletName = me.store?.name,
                    cashierId = cashierId,
                    cashierName = cashierName,
                    roleLabel = me.user?.role?.takeUnless { it.isBlank() } ?: "",
                ),
                deviceUuid = deviceUuid,
                deviceName = deviceName,
                installationId = installationId,
                appVersionName = appVersionName,
                appVersionCode = appVersionCode,
                buildType = buildType,
                sessionValid = sessionValid,
            )
        }
    }
}
