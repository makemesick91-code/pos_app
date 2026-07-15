package com.aishtech.poslite.feature.startup

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.aishtech.poslite.core.network.NetworkMonitor
import com.aishtech.poslite.core.runtime.ActivationStateStore
import com.aishtech.poslite.core.runtime.DeviceStatus
import com.aishtech.poslite.core.runtime.DeviceTrust
import com.aishtech.poslite.core.runtime.RuntimeContextStore
import com.aishtech.poslite.core.session.LogoutGuard
import com.aishtech.poslite.core.session.SessionManager
import com.aishtech.poslite.core.session.UnsyncedCounter
import com.aishtech.poslite.core.startup.BootState
import com.aishtech.poslite.core.startup.StartupCoordinator
import com.aishtech.poslite.core.startup.StartupInputs
import com.aishtech.poslite.data.repository.AuthRepository
import com.aishtech.poslite.data.repository.DeviceActivationRepository
import com.aishtech.poslite.data.repository.SessionCheck
import kotlinx.coroutines.CoroutineDispatcher
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import kotlinx.coroutines.withTimeoutOrNull

/**
 * UIX-8C-07 — drives the deterministic startup/auth state machine (UIX8C-R211).
 * It performs the ordered checks (emitting bounded progress states), gathers the
 * facts into [StartupInputs], and lets the pure [StartupCoordinator] decide the
 * destination. It never scatters navigation decisions; a single [state] drives
 * the Activity. Startup is bounded by [STARTUP_TIMEOUT_MS] so it can never trap on
 * an infinite splash (UIX8C-R215).
 */
class StartupViewModel(
    private val session: SessionManager,
    private val authRepo: AuthRepository,
    private val deviceRepo: DeviceActivationRepository,
    private val unsyncedCounter: UnsyncedCounter,
    private val contextStore: RuntimeContextStore,
    private val activationState: ActivationStateStore,
    private val networkMonitor: NetworkMonitor,
    private val coordinator: StartupCoordinator = StartupCoordinator(),
    private val dispatcher: CoroutineDispatcher = Dispatchers.IO,
) : ViewModel() {

    private val _state = MutableLiveData<BootState>(BootState.Bootstrapping)
    val state: LiveData<BootState> = _state

    fun start() {
        viewModelScope.launch {
            _state.value = BootState.DatabaseMigration
            val result = withContext(dispatcher) {
                withTimeoutOrNull(STARTUP_TIMEOUT_MS) { gather() }
            }
            _state.value = result ?: BootState.RecoverableFailure(
                "Memulai aplikasi memerlukan waktu terlalu lama. Coba lagi.",
            )
        }
    }

    private suspend fun gather(): BootState {
        // Restore secure runtime context + read local facts.
        val hasToken = session.isLoggedIn()
        val online = networkMonitor.isOnline()
        val activationPresent = activationState.isActivated()

        // Server-authoritative device status (only meaningful when reached).
        val status: DeviceStatus = if (online) deviceRepo.status() else DeviceStatus.unknown()
        val statusReached = status.trust != DeviceTrust.UNKNOWN

        // Session verification (distinguishes expired vs unreachable).
        var sessionValid = false
        var sessionExpired = false
        var resolvedTenantId: Long? = status.tenantId
        var cashierAuthorized = true
        if (hasToken && online) {
            when (val check = authRepo.verifySession()) {
                is SessionCheck.Valid -> {
                    sessionValid = true
                    resolvedTenantId = check.me.tenant?.id ?: resolvedTenantId
                    cashierAuthorized = check.me.user?.role != null
                }
                SessionCheck.Expired -> sessionExpired = true
                SessionCheck.Unreachable -> { /* offline continuation path */ }
            }
        }

        val tenantConsistent = contextStore.isConsistentWith(resolvedTenantId)
        val pending = runCatching { unsyncedCounter.pendingCount() }.getOrDefault(0)

        val inputs = StartupInputs(
            dbReady = true,
            activationPresent = activationPresent,
            deviceStatusReached = statusReached,
            deviceRevoked = status.revoked,
            revocationReason = status.revocationReason,
            deviceActive = status.active,
            hasStoredSession = hasToken,
            sessionValid = sessionValid,
            sessionExpired = sessionExpired,
            tenantConsistent = tenantConsistent,
            cashierAuthorized = cashierAuthorized,
            pendingUnsynced = pending,
        )
        return coordinator.evaluate(inputs)
    }

    companion object {
        const val STARTUP_TIMEOUT_MS = 12_000L
    }
}
