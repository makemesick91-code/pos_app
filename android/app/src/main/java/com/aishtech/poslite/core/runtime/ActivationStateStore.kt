package com.aishtech.poslite.core.runtime

import android.content.Context

/**
 * UIX-8C-07 — a tiny, device-scoped flag recording that this installation has
 * completed device activation. It is NOT the authority on validity (the server is,
 * via the device-status poll) — it only lets the startup state machine choose
 * between the activation screen and the login/session path (UIX8C-R217). It is
 * cleared on a tenant reset (device re-activation required), never on a normal
 * logout (the device stays activated across cashier sessions).
 */
interface ActivationStateStore {
    fun isActivated(): Boolean
    fun markActivated()
    fun clear()
}

class SharedPrefsActivationStateStore(context: Context) : ActivationStateStore {

    private val prefs = context.applicationContext
        .getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    override fun isActivated(): Boolean = prefs.getBoolean(KEY_ACTIVATED, false)

    override fun markActivated() {
        prefs.edit().putBoolean(KEY_ACTIVATED, true).apply()
    }

    override fun clear() {
        prefs.edit().remove(KEY_ACTIVATED).apply()
    }

    private companion object {
        const val PREFS_NAME = "aish_pos_activation"
        const val KEY_ACTIVATED = "device_activated"
    }
}
