package com.aishtech.poslite.core.runtime

import android.content.Context

/**
 * UIX-8C-07 — a tiny, device-scoped flag recording that this installation has
 * completed device activation. It is NOT the authority on validity (the server is,
 * via the device-status poll) — it only lets the startup state machine choose
 * between the activation screen and the login/session path (UIX8C-R217). It is
 * cleared on a tenant reset (device re-activation required), never on a normal
 * logout (the device stays activated across cashier sessions).
 *
 * UIX-8C-08 (DEF-006) — it additionally caches the LAST SERVER-AUTHORITATIVE
 * revocation verdict. The server remains the only authority that can *declare* a
 * revocation, but once it has said "revoked" that verdict MUST survive going
 * offline: otherwise a revoked (e.g. lost/stolen) device regains the cashier
 * surface simply by enabling airplane mode and restarting, which UIX8C-R220
 * forbids ("…MUST NOT be bypassable via back navigation, deep link, process
 * restart, or offline mode"). The cached verdict is cleared only on a
 * server-confirmed reactivation or a tenant reset — never by merely losing
 * connectivity.
 */
interface ActivationStateStore {
    fun isActivated(): Boolean
    fun markActivated()
    fun clear()

    /** Cache a server-confirmed revocation so it is enforced while offline. */
    fun markRevoked(reason: String?)

    /** Clear the cached revocation after a server-confirmed active/reactivated status. */
    fun clearRevoked()

    /** True when the server has previously confirmed this device is revoked. */
    fun isRevokedKnown(): Boolean

    /** The human-safe reason captured with the cached revocation (may be null). */
    fun revokedReason(): String?
}

class SharedPrefsActivationStateStore(context: Context) : ActivationStateStore {

    private val prefs = context.applicationContext
        .getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    override fun isActivated(): Boolean = prefs.getBoolean(KEY_ACTIVATED, false)

    override fun markActivated() {
        // A fresh activation supersedes any cached revocation for this install.
        prefs.edit().putBoolean(KEY_ACTIVATED, true)
            .remove(KEY_REVOKED).remove(KEY_REVOKED_REASON).apply()
    }

    override fun clear() {
        prefs.edit().remove(KEY_ACTIVATED).remove(KEY_REVOKED).remove(KEY_REVOKED_REASON).apply()
    }

    override fun markRevoked(reason: String?) {
        prefs.edit().putBoolean(KEY_REVOKED, true).putString(KEY_REVOKED_REASON, reason).apply()
    }

    override fun clearRevoked() {
        prefs.edit().remove(KEY_REVOKED).remove(KEY_REVOKED_REASON).apply()
    }

    override fun isRevokedKnown(): Boolean = prefs.getBoolean(KEY_REVOKED, false)

    override fun revokedReason(): String? = prefs.getString(KEY_REVOKED_REASON, null)

    private companion object {
        const val PREFS_NAME = "aish_pos_activation"
        const val KEY_ACTIVATED = "device_activated"
        const val KEY_REVOKED = "device_revoked_known"
        const val KEY_REVOKED_REASON = "device_revoked_reason"
    }
}
