package com.aishtech.poslite.feature.settings

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.aishtech.poslite.core.network.NetworkMonitor
import com.aishtech.poslite.core.runtime.DeviceStatus
import com.aishtech.poslite.core.runtime.DeviceTrust
import com.aishtech.poslite.core.session.ConnectionStatus
import com.aishtech.poslite.core.session.LocalDataCleaner
import com.aishtech.poslite.core.session.CleanupOp
import com.aishtech.poslite.core.session.LogoutDecision
import com.aishtech.poslite.core.session.LogoutGuard
import com.aishtech.poslite.core.session.SessionManager
import com.aishtech.poslite.core.session.SessionStateUi
import com.aishtech.poslite.core.session.StatusChip
import com.aishtech.poslite.core.session.SyncStatusUi
import com.aishtech.poslite.core.session.UnsyncedCounter
import com.aishtech.poslite.data.repository.AuthRepository
import com.aishtech.poslite.data.repository.DeviceActivationRepository
import com.aishtech.poslite.data.repository.SessionCheck
import kotlinx.coroutines.launch

/** Static application/device build facts for the Settings "Application" section. */
data class AppBuildInfo(
    val versionName: String,
    val versionCode: Long,
    val buildType: String,
    val packageName: String,
    val androidRelease: String,
    val deviceModel: String,
    val installationIdShort: String,
)

/**
 * UIX-8C-07 — the Settings snapshot. Every value is truthful: an unknown value is
 * [UNAVAILABLE] ("Tidak tersedia"), never a fabricated blank or a green status
 * from mere configuration (UIX8C-R242/R245). Secrets, tokens, the raw activation
 * code, and raw encryption identifiers are never included (UIX8C-R246).
 */
data class SettingsSnapshot(
    val tenantName: String,
    val outletName: String,
    val cashierName: String,
    val roleLabel: String,
    val deviceName: String,
    val activationStatusLabel: String,
    val activatedAt: String,
    val lastSeenAt: String,
    val appVersionName: String,
    val appVersionCode: String,
    val buildType: String,
    val packageName: String,
    val androidRelease: String,
    val deviceModel: String,
    val installationIdShort: String,
    val connection: StatusChip,
    val session: StatusChip,
    val sync: StatusChip,
    val pendingUnsynced: Int,
) {
    companion object {
        const val UNAVAILABLE = "Tidak tersedia"
    }
}

class SettingsViewModel(
    private val authRepo: AuthRepository,
    private val deviceRepo: DeviceActivationRepository,
    private val logoutGuard: LogoutGuard,
    private val cleaner: LocalDataCleaner,
    private val session: SessionManager,
    private val unsyncedCounter: UnsyncedCounter,
    private val networkMonitor: NetworkMonitor,
    private val appBuildInfo: AppBuildInfo,
) : ViewModel() {

    sealed interface LogoutOutcome {
        data object LoggedOut : LogoutOutcome
        data class Blocked(val pending: Int, val failed: Int) : LogoutOutcome
    }

    private val _snapshot = MutableLiveData<SettingsSnapshot>()
    val snapshot: LiveData<SettingsSnapshot> = _snapshot

    private val _logout = MutableLiveData<LogoutOutcome?>()
    val logout: LiveData<LogoutOutcome?> = _logout

    fun refresh() {
        // Emit a truthful "checking" snapshot immediately.
        _snapshot.value = buildSnapshot(
            me = null,
            status = DeviceStatus.unknown(),
            connection = ConnectionStatus.CHECKING,
            sessionState = SessionStateUi.CHECKING,
            sync = SyncStatusUi.UNAVAILABLE,
            pending = 0,
        )
        viewModelScope.launch {
            val online = networkMonitor.isOnline()
            val status = if (online) deviceRepo.status() else DeviceStatus.unknown()
            val check = if (online) authRepo.verifySession() else SessionCheck.Unreachable

            val me = (check as? SessionCheck.Valid)?.me
            val connection = when {
                !online -> ConnectionStatus.DISCONNECTED
                me != null -> ConnectionStatus.CONNECTED
                else -> ConnectionStatus.DEGRADED
            }
            val sessionState = when (check) {
                is SessionCheck.Valid -> SessionStateUi.ACTIVE
                SessionCheck.Expired -> SessionStateUi.SESSION_EXPIRED
                SessionCheck.Unreachable -> if (session.isLoggedIn()) SessionStateUi.UNAVAILABLE else SessionStateUi.SESSION_EXPIRED
            }.let { if (status.revoked) SessionStateUi.DEVICE_REVOKED else it }

            val pending = runCatching { unsyncedCounter.pendingCount() }.getOrDefault(0)
            val failed = runCatching { unsyncedCounter.failedCount() }.getOrDefault(0)
            val sync = when {
                failed > 0 -> SyncStatusUi.SYNC_FAILED
                pending > 0 -> SyncStatusUi.SYNC_PENDING
                else -> SyncStatusUi.SYNCED
            }

            _snapshot.value = buildSnapshot(me, status, connection, sessionState, sync, pending + failed)
        }
    }

    /** Attempt logout: blocked while any un-acked transaction exists (UIX8C-R230). */
    fun attemptLogout() {
        viewModelScope.launch {
            when (val decision = logoutGuard.evaluate()) {
                LogoutDecision.Allowed -> {
                    authRepo.logout()
                    // Clear only cashier-scoped state; the device stays activated.
                    cleaner.clear(CleanupOp.LOGOUT)
                    _logout.value = LogoutOutcome.LoggedOut
                }
                is LogoutDecision.BlockedByUnsynced ->
                    _logout.value = LogoutOutcome.Blocked(decision.pending, decision.failed)
            }
        }
    }

    fun consumeLogout() {
        _logout.value = null
    }

    private fun buildSnapshot(
        me: com.aishtech.poslite.data.remote.dto.MeResponse?,
        status: DeviceStatus,
        connection: ConnectionStatus,
        sessionState: SessionStateUi,
        sync: SyncStatusUi,
        pending: Int,
    ): SettingsSnapshot = SettingsSnapshot(
        tenantName = me?.tenant?.name ?: status.tenantName.orUnavailable(),
        outletName = me?.store?.name ?: status.outletName.orUnavailable(),
        cashierName = me?.user?.name.orUnavailable(),
        roleLabel = me?.user?.role.orUnavailable(),
        deviceName = status.deviceName ?: SettingsSnapshot.UNAVAILABLE,
        activationStatusLabel = activationLabel(status.trust),
        activatedAt = status.activatedAt.orUnavailable(),
        lastSeenAt = status.lastSeenAt.orUnavailable(),
        appVersionName = appBuildInfo.versionName,
        appVersionCode = appBuildInfo.versionCode.toString(),
        buildType = appBuildInfo.buildType,
        packageName = appBuildInfo.packageName,
        androidRelease = appBuildInfo.androidRelease,
        deviceModel = appBuildInfo.deviceModel,
        installationIdShort = appBuildInfo.installationIdShort,
        connection = StatusChip.of(connection),
        session = StatusChip.of(sessionState),
        sync = StatusChip.of(sync),
        pendingUnsynced = pending,
    )

    private fun activationLabel(trust: DeviceTrust): String = when (trust) {
        DeviceTrust.ACTIVE -> "Aktif"
        DeviceTrust.REVOKED -> "Dinonaktifkan"
        DeviceTrust.EXPIRED -> "Kedaluwarsa"
        DeviceTrust.NOT_ACTIVATED -> "Belum diaktifkan"
        DeviceTrust.INACTIVE -> "Tidak aktif"
        DeviceTrust.UNKNOWN -> SettingsSnapshot.UNAVAILABLE
    }

    private fun String?.orUnavailable(): String =
        this?.trim()?.takeUnless { it.isEmpty() } ?: SettingsSnapshot.UNAVAILABLE
}
