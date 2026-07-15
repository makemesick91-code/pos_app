package com.aishtech.poslite.feature.session

import android.content.Intent
import android.os.Bundle
import androidx.activity.OnBackPressedCallback
import androidx.appcompat.app.AppCompatActivity
import com.aishtech.poslite.databinding.ActivitySessionExpiredBinding
import com.aishtech.poslite.feature.auth.LoginActivity

/**
 * UIX-8C-07 — the session-expired recovery screen (UIX8C-R233). It locks the UI to
 * re-authentication and preserves pending offline transactions (nothing is cleared
 * here). Back navigation also routes to login so a stale authenticated screen can
 * never be resumed.
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
        startActivity(Intent(this, LoginActivity::class.java))
        finish()
    }
}
