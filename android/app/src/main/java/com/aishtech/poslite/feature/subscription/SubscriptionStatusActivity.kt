package com.aishtech.poslite.feature.subscription

import android.content.Intent
import android.os.Bundle
import android.view.View
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.lifecycleScope
import com.aishtech.poslite.core.ServiceLocator
import com.aishtech.poslite.databinding.ActivitySubscriptionStatusBinding
import com.aishtech.poslite.feature.auth.LoginActivity
import com.aishtech.poslite.feature.cashier.CashierActivity
import kotlinx.coroutines.launch

/**
 * Lightweight subscription/device status screen (Sprint 10). Shows the
 * backend-computed status, plan limits, and active device count. When the tenant
 * is blocked (expired subscription / device limit reached) the cashier is not
 * reachable from here. No billing, upgrade, or Play Billing UI exists.
 */
class SubscriptionStatusActivity : AppCompatActivity() {

    private lateinit var binding: ActivitySubscriptionStatusBinding
    private lateinit var viewModel: SubscriptionStatusViewModel

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivitySubscriptionStatusBinding.inflate(layoutInflater)
        setContentView(binding.root)

        val subscriptions = ServiceLocator.subscriptionRepository(applicationContext)
        viewModel = ViewModelProvider(
            this,
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T =
                    SubscriptionStatusViewModel(subscriptions) as T
            },
        )[SubscriptionStatusViewModel::class.java]

        viewModel.state.observe(this) { render(it) }

        binding.buttonRetry.setOnClickListener { retry() }
        binding.buttonBack.setOnClickListener { logout() }

        viewModel.refresh()
    }

    private fun render(state: SubscriptionStatusViewModel.UiState) {
        when (state) {
            SubscriptionStatusViewModel.UiState.Loading -> {
                binding.progress.visibility = View.VISIBLE
                binding.textError.visibility = View.GONE
            }
            is SubscriptionStatusViewModel.UiState.Ready -> {
                binding.progress.visibility = View.GONE
                binding.textError.visibility = View.GONE
                val model = state.model
                binding.textStatus.text = model.statusLabel
                binding.textPlan.text = model.planLabel
                binding.textDevices.text = model.deviceLabel
                if (model.reason != null) {
                    binding.textReason.text = model.reason
                    binding.textReason.visibility = View.VISIBLE
                } else {
                    binding.textReason.visibility = View.GONE
                }
            }
            is SubscriptionStatusViewModel.UiState.Error -> {
                binding.progress.visibility = View.GONE
                binding.textError.text = state.message
                binding.textError.visibility = View.VISIBLE
            }
        }
    }

    /**
     * Re-check the status. If the tenant is now allowed and this device can be
     * registered, continue into the cashier; otherwise stay blocked.
     */
    private fun retry() {
        val devices = ServiceLocator.deviceRepository(applicationContext)
        val subscriptions = ServiceLocator.subscriptionRepository(applicationContext)
        binding.progress.visibility = View.VISIBLE
        lifecycleScope.launch {
            when (val status = subscriptions.getStatus()) {
                is com.aishtech.poslite.core.util.ResultState.Success -> {
                    if (SubscriptionStatusDisplay.isAllowed(status.data)) {
                        when (devices.registerCurrentDevice()) {
                            is com.aishtech.poslite.core.util.ResultState.Success -> openCashier()
                            else -> viewModel.refresh()
                        }
                    } else {
                        viewModel.refresh()
                    }
                }
                else -> viewModel.refresh()
            }
        }
    }

    private fun openCashier() {
        startActivity(Intent(this, CashierActivity::class.java))
        finish()
    }

    private fun logout() {
        ServiceLocator.authRepository(applicationContext).let { repo ->
            lifecycleScope.launch {
                repo.logout()
                startActivity(
                    Intent(this@SubscriptionStatusActivity, LoginActivity::class.java)
                        .addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_NEW_TASK),
                )
                finish()
            }
        }
    }
}
