package com.aishtech.poslite.feature.activation

import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.aishtech.poslite.core.ServiceLocator
import com.aishtech.poslite.databinding.ActivityDeviceActivationBinding
import com.aishtech.poslite.feature.auth.LoginActivity

/**
 * UIX-8C-07 — device activation entry (UIX8C-R217). The operator enters the
 * single-use activation code issued out-of-band; on server-confirmed success the
 * login screen opens (tenant/outlet confirmation before cashier login). The raw
 * code is never logged.
 */
class DeviceActivationActivity : AppCompatActivity() {

    private lateinit var binding: ActivityDeviceActivationBinding
    private lateinit var viewModel: DeviceActivationViewModel

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityDeviceActivationBinding.inflate(layoutInflater)
        setContentView(binding.root)

        viewModel = ViewModelProvider(
            this,
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T =
                    ServiceLocator.buildDeviceActivationViewModel(applicationContext) as T
            },
        )[DeviceActivationViewModel::class.java]

        binding.buttonActivate.setOnClickListener {
            viewModel.activate(binding.inputCode.text?.toString().orEmpty())
        }
        binding.inputCode.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) = viewModel.resetError()
        })

        viewModel.state.observe(this) { render(it) }
    }

    private fun render(state: DeviceActivationViewModel.State) {
        val submitting = state is DeviceActivationViewModel.State.Submitting
        binding.progress.visibility = if (submitting) View.VISIBLE else View.GONE
        binding.buttonActivate.isEnabled = !submitting

        when (state) {
            DeviceActivationViewModel.State.Idle,
            DeviceActivationViewModel.State.Submitting,
            -> binding.textError.visibility = View.GONE

            is DeviceActivationViewModel.State.Rejected -> {
                binding.textError.visibility = View.VISIBLE
                binding.textError.text = state.message
            }

            is DeviceActivationViewModel.State.Activated -> {
                Toast.makeText(this, "Perangkat diaktifkan.", Toast.LENGTH_SHORT).show()
                startActivity(android.content.Intent(this, LoginActivity::class.java))
                finish()
            }
        }
    }
}
