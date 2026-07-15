package com.aishtech.poslite.feature.session

import android.os.Bundle
import android.view.View
import androidx.activity.OnBackPressedCallback
import androidx.appcompat.app.AppCompatActivity
import com.aishtech.poslite.databinding.ActivityDeviceRevokedBinding

/**
 * UIX-8C-07 — the fail-closed revoked/invalid-device screen (UIX8C-R220/R234). It
 * renders NO tenant data, offers no path back into the app, and cannot be bypassed
 * by back navigation, deep link, or process restart. The pending queue stays
 * quarantined (never surfaced, never moved to another tenant). No token/secret is
 * ever shown.
 */
class DeviceRevokedActivity : AppCompatActivity() {

    private lateinit var binding: ActivityDeviceRevokedBinding

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityDeviceRevokedBinding.inflate(layoutInflater)
        setContentView(binding.root)

        val reason = intent.getStringExtra(EXTRA_REASON)?.takeUnless { it.isBlank() }
            ?: "Perangkat ini telah dinonaktifkan. Hubungi admin toko untuk mengaktifkan kembali perangkat."
        binding.textReason.text = reason

        bindOptionalRow(binding.rowDevice, binding.textDeviceName, intent.getStringExtra(EXTRA_DEVICE_NAME))
        bindOptionalRow(binding.rowOutlet, binding.textOutletName, intent.getStringExtra(EXTRA_OUTLET_NAME))

        binding.buttonClose.setOnClickListener { finishAffinity() }
        // Fail closed: back never returns to any tenant screen.
        onBackPressedDispatcher.addCallback(
            this,
            object : OnBackPressedCallback(true) {
                override fun handleOnBackPressed() = finishAffinity()
            },
        )
    }

    private fun bindOptionalRow(row: View, value: android.widget.TextView, text: String?) {
        if (text.isNullOrBlank()) {
            row.visibility = View.GONE
        } else {
            row.visibility = View.VISIBLE
            value.text = text
        }
    }

    companion object {
        const val EXTRA_REASON = "reason"
        const val EXTRA_DEVICE_NAME = "device_name"
        const val EXTRA_OUTLET_NAME = "outlet_name"
    }
}
