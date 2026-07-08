package com.aishtech.poslite.core.network

/**
 * Sprint 26 — maps server-side tenant plan entitlement / usage-limit enforcement
 * responses to a friendly, non-technical message for the POS user.
 *
 * The Android/POS client is UX only and is NEVER the enforcement authority
 * (TPE-R010): the entitlement/limit decision is always made server-side. When the
 * backend returns HTTP 403 with code FEATURE_NOT_ENTITLED the client informs the
 * operator that the feature is not on their plan; when it returns HTTP 429 with
 * code USAGE_LIMIT_EXCEEDED it informs them the plan limit is reached. The client
 * must not attempt to bypass or self-authorize.
 */
object TenantPlanMessages {

    const val CODE_FEATURE_NOT_ENTITLED = "FEATURE_NOT_ENTITLED"
    const val CODE_USAGE_LIMIT_EXCEEDED = "USAGE_LIMIT_EXCEEDED"

    const val HTTP_FORBIDDEN = 403
    const val HTTP_TOO_MANY_REQUESTS = 429

    private const val FEATURE_MESSAGE =
        "Fitur ini tidak tersedia pada paket langganan Anda. Silakan hubungi admin/penyedia layanan untuk meng-upgrade."

    private const val LIMIT_MESSAGE =
        "Batas penggunaan paket langganan Anda telah tercapai. Silakan hubungi admin/penyedia layanan untuk meng-upgrade."

    /**
     * Returns a friendly plan message when the response signals an entitlement or
     * usage-limit block (by known code, or by HTTP status), otherwise null.
     */
    fun messageFor(httpCode: Int, errorCode: String?): String? {
        return when {
            errorCode == CODE_FEATURE_NOT_ENTITLED -> FEATURE_MESSAGE
            errorCode == CODE_USAGE_LIMIT_EXCEEDED -> LIMIT_MESSAGE
            httpCode == HTTP_TOO_MANY_REQUESTS -> LIMIT_MESSAGE
            else -> null
        }
    }

    fun isFeatureNotEntitled(errorCode: String?): Boolean =
        errorCode == CODE_FEATURE_NOT_ENTITLED

    fun isUsageLimitExceeded(httpCode: Int, errorCode: String?): Boolean =
        errorCode == CODE_USAGE_LIMIT_EXCEEDED || httpCode == HTTP_TOO_MANY_REQUESTS

    fun isPlanBlock(httpCode: Int, errorCode: String?): Boolean =
        messageFor(httpCode, errorCode) != null
}
