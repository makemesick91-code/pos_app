package com.aishtech.poslite.feature.session

import android.content.Intent
import android.os.Bundle
import androidx.activity.OnBackPressedCallback
import androidx.appcompat.app.AppCompatActivity
import com.aishtech.poslite.core.ServiceLocator
import com.aishtech.poslite.databinding.ActivitySessionExpiredBinding
import com.aishtech.poslite.feature.auth.LoginActivity

/**
 * UIX-8C-07 — the session-expired recovery screen (UIX8C-R233). It locks the UI to
 * re-authentication and preserves pending offline transactions (nothing is cleared
 * here). Back navigation also routes to login so a stale authenticated screen can
 * never be resumed.
 *
 * UIX-8C-08 (DEF-003) — the rejected session token MUST be cleared before routing
 * to login. `TokenStore.isLoggedIn()` only asserts that a token STRING exists, not
 * that it is still valid, and `LoginActivity` short-circuits to the cashier when
 * `isLoggedIn()` is true. Leaving the server-rejected token in place therefore
 * bounced SessionExpired -> Login -> Cashier, handing the operator a fully
 * operable cashier surface with no valid session (found on physical hardware).
 * Only the auth token is cleared here — unsynced offline transactions in Room are
 * never touched (UIX8C-R229).
 */
class SessionExpiredActivity : AppCompatActivity() {

    private lateinit var binding: ActivitySessionExpiredBinding

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivitySessionExpiredBinding.inflate(layoutInflater)
        setContentView(binding.root)

        binding.buttonRelogin.setOnClickListener { goToLogin() }
        onBackPressedDispatcher.addCallback(
            this,
            object : OnBackPressedCallback(true) {
                override fun handleOnBackPressed() = goToLogin()
            },
        )
    }

    private fun goToLogin() {
        // DEF-003: end the rejected session first so re-authentication is actually
        // REQUIRED (isLoggedIn() must be false when LoginActivity evaluates it).
        // Offline/unsynced transactions live in Room and are deliberately untouched.
        ServiceLocator.session(applicationContext).endSession()
        startActivity(Intent(this, LoginActivity::class.java))
        finish()
    }
}
