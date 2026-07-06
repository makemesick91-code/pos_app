package com.aishtech.poslite

import android.content.Intent
import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity
import com.aishtech.poslite.core.ServiceLocator
import com.aishtech.poslite.feature.auth.LoginActivity
import com.aishtech.poslite.feature.cashier.CashierActivity

/**
 * Launcher/router. Routes to the cashier screen when a session token exists,
 * otherwise to login. No UI of its own — the cashier foundation lives in the
 * feature packages per ../../docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md.
 */
class MainActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val target = if (ServiceLocator.session(applicationContext).isLoggedIn()) {
            CashierActivity::class.java
        } else {
            LoginActivity::class.java
        }
        startActivity(Intent(this, target))
        finish()
    }
}
