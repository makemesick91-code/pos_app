package com.aishtech.poslite.feature.settings

import android.content.Intent
import android.os.Bundle
import android.view.View
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.aishtech.poslite.core.ServiceLocator
import com.aishtech.poslite.core.session.StatusChip
import com.aishtech.poslite.databinding.ActivitySettingsBinding
import com.aishtech.poslite.feature.auth.LoginActivity

/**
 * UIX-8C-07 — the premium operational Settings surface (UIX8C-R245/R246/R247). It
 * presents truthful Account/Context, Device, Application, Connection, Sync,
 * Printer, and Security/Session values over the canonical repositories — unknowns
 * render "Tidak tersedia", and no token/secret/activation-code is ever shown.
 * Logout is guarded by the unsynced-transaction gate (UIX8C-R230).
 */
class SettingsActivity : AppCompatActivity() {

    private lateinit var binding: ActivitySettingsBinding
    private lateinit var viewModel: SettingsViewModel

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivitySettingsBinding.inflate(layoutInflater)
        setContentView(binding.root)

        viewModel = ViewModelProvider(
            this,
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T =
                    ServiceLocator.buildSettingsViewModel(applicationContext) as T
            },
        )[SettingsViewModel::class.java]

        binding.buttonCheckConnection.setOnClickListener { viewModel.refresh() }
        binding.buttonSyncNow.setOnClickListener { viewModel.refresh() }
        binding.buttonLogoutAccount.setOnClickListener { viewModel.attemptLogout() }
        binding.buttonLogoutSecurity.setOnClickListener { viewModel.attemptLogout() }
        binding.buttonSwitchAccount.setOnClickListener { viewModel.attemptLogout() }
        binding.buttonPrinterSettings.visibility = View.GONE

        viewModel.snapshot.observe(this) { render(it) }
        viewModel.logout.observe(this) { it?.let { outcome -> onLogout(outcome) } }
    }

    override fun onResume() {
        super.onResume()
        viewModel.refresh()
    }

    private fun render(s: SettingsSnapshot) {
        binding.valueTenant.text = s.tenantName
        binding.valueOutlet.text = s.outletName
        binding.valueCashier.text = s.cashierName
        binding.valueRole.text = s.roleLabel
        binding.valueDeviceName.text = s.deviceName
        binding.valueActivation.text = s.activationStatusLabel
        binding.valueActivatedAt.text = s.activatedAt
        binding.valueLastSeen.text = s.lastSeenAt
        binding.valueInstallation.text = s.installationIdShort
        binding.valueAppVersion.text = s.appVersionName
        binding.valueVersionCode.text = s.appVersionCode
        binding.valueBuildType.text = s.buildType
        binding.valuePackage.text = s.packageName
        binding.valueAndroid.text = s.androidRelease
        binding.valueDeviceModel.text = s.deviceModel
        bindChip(binding.chipConnection, s.connection)
        bindChip(binding.chipSync, s.sync)
        bindChip(binding.chipSession, s.session)
        binding.textPending.text = if (s.pendingUnsynced > 0) {
            "${s.pendingUnsynced} transaksi menunggu sinkronisasi"
        } else {
            "Tidak ada transaksi tertunda"
        }
    }

    /** The text label is mandatory (status is never colour-alone, UIX8C-R244). */
    private fun bindChip(view: android.widget.TextView, chip: StatusChip) {
        view.text = chip.label
    }

    private fun onLogout(outcome: SettingsViewModel.LogoutOutcome) {
        when (outcome) {
            SettingsViewModel.LogoutOutcome.LoggedOut -> {
                viewModel.consumeLogout()
                startActivity(Intent(this, LoginActivity::class.java))
                finishAffinity()
            }
            is SettingsViewModel.LogoutOutcome.Blocked -> {
                viewModel.consumeLogout()
                val total = outcome.pending + outcome.failed
                AlertDialog.Builder(this)
                    .setTitle("Logout belum dapat dilakukan")
                    .setMessage(
                        "Masih ada $total transaksi yang menunggu sinkronisasi. " +
                            "Sinkronkan transaksi terlebih dahulu agar data tidak hilang.",
                    )
                    .setPositiveButton("Sync sekarang") { d, _ ->
                        viewModel.refresh()
                        d.dismiss()
                    }
                    .setNegativeButton("Kembali") { d, _ -> d.dismiss() }
                    .show()
            }
        }
    }
}
