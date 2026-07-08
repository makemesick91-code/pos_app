package com.aishtech.poslite.core.runtime

/**
 * Sprint 34 — the client-side view of the server's Android runtime posture
 * (ADR-R001/R007/R008/R009/R025).
 *
 * The Android/POS client is UX only and NEVER the enforcement authority: the
 * backend `AndroidRuntimeAccessService` decides allowed/read-only/blocked. This
 * type maps the server `status`/`reason_code` (from GET /api/v1/android/runtime/policy
 * or a runtime decision on a denied write) to whether the client should permit a
 * write and to a friendly operator message. When the local snapshot is stale the
 * client fails SAFE to read-only (ADR-R025) — it never assumes access.
 */
enum class RuntimeStatus(val wire: String) {
    ALLOWED("allowed"),
    DEGRADED("degraded"),
    READ_ONLY("read_only"),
    BLOCKED("blocked"),
    DENIED("denied"),
    UNKNOWN("unknown");

    companion object {
        fun fromWire(value: String?): RuntimeStatus =
            entries.firstOrNull { it.wire.equals(value, ignoreCase = true) } ?: UNKNOWN
    }
}

data class AndroidRuntimePosture(
    val status: RuntimeStatus,
    val reasonCode: String?,
    val stale: Boolean = false,
) {
    /** A write (sale/sync) may proceed only when the server allows it and the
     *  snapshot is fresh; a stale snapshot degrades to read-only (ADR-R025). */
    val writeAllowed: Boolean
        get() = !stale && (status == RuntimeStatus.ALLOWED || status == RuntimeStatus.DEGRADED)

    val readOnly: Boolean
        get() = !writeAllowed

    companion object {
        /** Fail-safe posture used before the first policy fetch or on a parse error. */
        fun failSafe(): AndroidRuntimePosture =
            AndroidRuntimePosture(RuntimeStatus.READ_ONLY, "STALE_SNAPSHOT", stale = true)
    }
}

object AndroidRuntimeMessages {

    const val HTTP_LOCKED = 423
    const val HTTP_PAYMENT_REQUIRED = 402

    private const val SUSPENDED =
        "Akses tenant sedang ditangguhkan. Silakan hubungi admin/penyedia layanan Anda."
    private const val UNPAID =
        "Tagihan langganan belum dibayar. Mode hanya-baca aktif hingga pembayaran diverifikasi."
    private const val TRIAL_EXPIRED =
        "Masa uji coba telah berakhir. Silakan aktifkan langganan untuk melanjutkan transaksi."
    private const val DEVICE_REVOKED =
        "Perangkat ini telah dinonaktifkan. Hubungi admin untuk mengaktifkan ulang."
    private const val READ_ONLY_GENERIC =
        "Mode hanya-baca aktif. Transaksi baru dinonaktifkan sementara."

    /**
     * Friendly, non-technical message for a denied/degraded runtime reason. Never
     * contains a token, signature or PII (ADR-R021/R022). Returns null when the
     * posture allows writes.
     */
    fun messageFor(reasonCode: String?): String? = when (reasonCode?.uppercase()) {
        "MANUALLY_SUSPENDED", "TENANT_SUSPENDED" -> SUSPENDED
        "UNPAID_PAST_GRACE", "MISSING_SUBSCRIPTION", "SUBSCRIPTION_CANCELLED" -> UNPAID
        "TRIAL_EXPIRED" -> TRIAL_EXPIRED
        "DEVICE_REVOKED", "DEVICE_EXPIRED", "DEVICE_NOT_ACTIVATED", "REGISTER_MISMATCH" -> DEVICE_REVOKED
        "STALE_SNAPSHOT", "READ_ONLY" -> READ_ONLY_GENERIC
        else -> null
    }
}
