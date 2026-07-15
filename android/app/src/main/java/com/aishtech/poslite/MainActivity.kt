package com.aishtech.poslite

import android.content.Intent
import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity
import com.aishtech.poslite.feature.startup.StartupActivity

/**
 * Launcher. UIX-8C-07 — the cold-start routing decision is NO LONGER a token-only
 * check here; it is delegated to the deterministic startup/auth state machine in
 * [StartupActivity] (UIX8C-R211/R216). MainActivity only hands off, so there is a
 * single authoritative place that decides activation vs login vs ready vs
 * revoked/expired/recovery. It renders no UI of its own.
 */
class MainActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        startActivity(Intent(this, StartupActivity::class.java))
        finish()
    }
}
