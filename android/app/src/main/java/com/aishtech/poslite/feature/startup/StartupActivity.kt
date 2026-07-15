package com.aishtech.poslite.feature.startup

import android.content.Intent
import android.os.Bundle
import android.view.View
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.aishtech.poslite.core.ServiceLocator
import com.aishtech.poslite.core.startup.BootState
import com.aishtech.poslite.databinding.ActivityStartupBinding
import com.aishtech.poslite.feature.activation.DeviceActivationActivity
import com.aishtech.poslite.feature.auth.LoginActivity
import com.aishtech.poslite.feature.cashier.CashierActivity
import com.aishtech.poslite.feature.session.DeviceRevokedActivity
import com.aishtech.poslite.feature.session.SessionExpiredActivity

/**
 * UIX-8C-07 — the single entry point that renders the deterministic startup/auth
 * state machine (UIX8C-R211/R216) and routes on the emitted [BootState]. It is the
 * ONLY place that decides activation vs login vs ready vs revoked/expired/recovery;
 * no other screen re-derives that decision.
 */
class StartupActivity : AppCompatActivity() {

    private lateinit var binding: ActivityStartupBinding
    private lateinit var viewModel: StartupViewModel

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityStartupBinding.inflate(layoutInflater)
        setContentView(binding.root)

        viewModel = ViewModelProvider(
            this,
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T =
                    ServiceLocator.buildStartupViewModel(applicationContext) as T
            },
        )[StartupViewModel::class.java]

        binding.buttonRetry.setOnClickListener { viewModel.start() }
        viewModel.state.observe(this) { render(it) }
        viewModel.start()
    }

    private fun render(state: BootState) {
        // Default: progress visible, error hidden.
        binding.errorContainer.visibility = View.GONE
        binding.progress.visibility = View.VISIBLE

        when (state) {
            BootState.Bootstrapping -> progress("Menyiapkan aplikasi")
            BootState.DatabaseMigration -> progress("Menyiapkan basis data")
            BootState.RestoringRuntime -> progress("Memulihkan sesi")
            BootState.Authenticating -> progress("Memeriksa perangkat & sesi")
            BootState.ActivatingDevice -> progress("Mengaktifkan perangkat")

            BootState.Ready, BootState.OfflineReady -> go(CashierActivity::class.java)
            BootState.ActivationRequired -> go(DeviceActivationActivity::class.java)
            BootState.LoginRequired -> go(LoginActivity::class.java)
            BootState.SessionExpired,
            BootState.ContextMismatch,
            BootState.RecoveryRequired,
            -> go(SessionExpiredActivity::class.java)

            is BootState.DeviceRevoked -> goRevoked(state.reason)
            BootState.DeviceInvalid -> goRevoked(
                "Perangkat ini tidak lagi terdaftar. Hubungi admin untuk mengaktifkan ulang.",
            )

            is BootState.RecoverableFailure -> showError(state.message)
            is BootState.FatalFailure -> showError(state.message)
        }
    }

    private fun progress(text: String) {
        binding.textProgress.text = text
    }

    private fun showError(message: String) {
        binding.progress.visibility = View.GONE
        binding.errorContainer.visibility = View.VISIBLE
        binding.textError.text = message
    }

    private fun go(target: Class<*>) {
        startActivity(Intent(this, target))
        finish()
    }

    private fun goRevoked(reason: String?) {
        startActivity(
            Intent(this, DeviceRevokedActivity::class.java)
                .putExtra(DeviceRevokedActivity.EXTRA_REASON, reason),
        )
        finish()
    }
}
