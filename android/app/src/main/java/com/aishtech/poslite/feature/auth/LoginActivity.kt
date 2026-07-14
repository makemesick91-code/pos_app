package com.aishtech.poslite.feature.auth

import android.content.Intent
import android.os.Bundle
import android.view.View
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.aishtech.poslite.core.ServiceLocator
import com.aishtech.poslite.core.util.EventObserver
import com.aishtech.poslite.databinding.ActivityLoginBinding
import com.aishtech.poslite.feature.cashier.CashierActivity
import com.aishtech.poslite.feature.subscription.SubscriptionStatusActivity

/**
 * Login screen consuming POST /api/v1/auth/login. On success the token is
 * stored (never the password) and the cashier screen opens.
 */
class LoginActivity : AppCompatActivity() {

    private lateinit var binding: ActivityLoginBinding
    private lateinit var viewModel: LoginViewModel

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityLoginBinding.inflate(layoutInflater)
        setContentView(binding.root)

        val repository = ServiceLocator.authRepository(applicationContext)
        if (repository.isLoggedIn()) {
            openCashier()
            return
        }

        val subscriptions = ServiceLocator.subscriptionRepository(applicationContext)
        val devices = ServiceLocator.deviceRepository(applicationContext)

        viewModel = ViewModelProvider(
            this,
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T =
                    LoginViewModel(repository, subscriptions, devices) as T
            },
        )[LoginViewModel::class.java]

        viewModel.state.observe(this) { state -> render(state) }
        // UIX8B-R008 — navigation fires once; rotation never re-launches it.
        viewModel.nav.observe(this, EventObserver { nav ->
            when (nav) {
                LoginViewModel.Nav.CASHIER -> openCashier()
                LoginViewModel.Nav.SUBSCRIPTION -> openSubscriptionStatus()
            }
        })

        binding.buttonLogin.setOnClickListener {
            binding.textError.visibility = View.GONE
            viewModel.login(
                binding.inputEmail.text?.toString().orEmpty(),
                binding.inputPassword.text?.toString().orEmpty(),
            )
        }
    }

    private fun render(state: LoginViewModel.UiState) {
        when (state) {
            LoginViewModel.UiState.Idle -> setLoading(false)
            LoginViewModel.UiState.Loading -> setLoading(true)
            is LoginViewModel.UiState.Blocked -> {
                setLoading(false)
                binding.textError.text = state.message
                binding.textError.visibility = View.VISIBLE
            }
            is LoginViewModel.UiState.Error -> {
                setLoading(false)
                binding.textError.text = state.message
                binding.textError.visibility = View.VISIBLE
            }
        }
    }

    private fun openSubscriptionStatus() {
        startActivity(Intent(this, SubscriptionStatusActivity::class.java))
    }

    private fun setLoading(loading: Boolean) {
        binding.progress.visibility = if (loading) View.VISIBLE else View.GONE
        binding.buttonLogin.isEnabled = !loading
    }

    private fun openCashier() {
        startActivity(Intent(this, CashierActivity::class.java))
        finish()
    }
}
