package com.aishtech.poslite

import android.os.Bundle
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity

/**
 * Sprint 0 placeholder entry point.
 *
 * Only confirms the Android skeleton launches. Business POS features
 * (cashier, products, QRIS, offline sync) are introduced in later sprints
 * per ../../docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md.
 */
class MainActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val message = TextView(this).apply {
            text = getString(R.string.sprint0_placeholder)
            textSize = 18f
            setPadding(48, 48, 48, 48)
        }
        setContentView(message)
    }
}
