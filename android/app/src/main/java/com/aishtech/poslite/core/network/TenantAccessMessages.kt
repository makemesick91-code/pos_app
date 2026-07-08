package com.aishtech.poslite.core.network

/**
 * Sprint 25 — maps server-side tenant lifecycle enforcement responses to a
 * friendly, non-technical message for the POS user.
 *
 * The Android/POS client is UX only and is NEVER the enforcement authority
 * (TLS-R009): the block is always decided server-side. When the backend returns
 * HTTP 423 with code TENANT_SUSPENDED, the client simply informs the operator to
 * contact the admin/provider; it must not attempt to bypass or self-authorize.
 */
object TenantAccessMessages {

    const val CODE_TENANT_SUSPENDED = "TENANT_SUSPENDED"
    const val CODE_TENANT_ARCHIVED = "TENANT_ARCHIVED"

    const val HTTP_LOCKED = 423

    private const val SUSPENDED_MESSAGE =
        "Akses tenant sedang ditangguhkan. Silakan hubungi admin/penyedia layanan Anda."

    /**
     * Returns a friendly suspension message when the response signals a
     * lifecycle block (HTTP 423 or a known lifecycle code), otherwise null.
     */
    fun messageFor(httpCode: Int, errorCode: String?): String? {
        val isLocked = httpCode == HTTP_LOCKED
        val isLifecycleCode = errorCode == CODE_TENANT_SUSPENDED || errorCode == CODE_TENANT_ARCHIVED

        return if (isLocked || isLifecycleCode) SUSPENDED_MESSAGE else null
    }

    fun isTenantSuspended(httpCode: Int, errorCode: String?): Boolean =
        messageFor(httpCode, errorCode) != null
}
