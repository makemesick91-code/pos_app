package com.aishtech.poslite.feature.printer

import android.content.Context
import android.content.SharedPreferences

/**
 * Local printer configuration (Sprint 6 foundation). SharedPreferences is an
 * acceptable store for Sprint 6.
 *
 * Rule: this store holds ONLY printer settings. It never holds payment gateway
 * credentials, user passwords, or auth tokens.
 */
data class PrinterSettings(
    val printerName: String? = null,
    val printerMacAddress: String? = null,
    val paperWidthMm: Int = 58,
    val autoCutEnabled: Boolean = true,
) {
    val isConfigured: Boolean
        get() = !printerMacAddress.isNullOrBlank()
}

class PrinterSettingsStore(context: Context) {

    private val prefs: SharedPreferences =
        context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    fun load(): PrinterSettings = PrinterSettings(
        printerName = prefs.getString(KEY_NAME, null),
        printerMacAddress = prefs.getString(KEY_MAC, null),
        paperWidthMm = prefs.getInt(KEY_WIDTH, 58),
        autoCutEnabled = prefs.getBoolean(KEY_AUTO_CUT, true),
    )

    fun save(settings: PrinterSettings) {
        prefs.edit()
            .putString(KEY_NAME, settings.printerName)
            .putString(KEY_MAC, settings.printerMacAddress)
            .putInt(KEY_WIDTH, settings.paperWidthMm)
            .putBoolean(KEY_AUTO_CUT, settings.autoCutEnabled)
            .apply()
    }

    fun clear() {
        prefs.edit().clear().apply()
    }

    private companion object {
        const val PREFS_NAME = "aish_pos_printer"
        const val KEY_NAME = "printer_name"
        const val KEY_MAC = "printer_mac_address"
        const val KEY_WIDTH = "paper_width_mm"
        const val KEY_AUTO_CUT = "auto_cut_enabled"
    }
}
