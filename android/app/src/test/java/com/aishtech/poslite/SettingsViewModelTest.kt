package com.aishtech.poslite

import androidx.arch.core.executor.testing.InstantTaskExecutorRule
import com.aishtech.poslite.core.network.NetworkMonitor
import com.aishtech.poslite.core.session.LocalDataCleaner
import com.aishtech.poslite.core.session.LogoutGuard
import com.aishtech.poslite.core.session.SessionManager
import com.aishtech.poslite.core.session.TokenStore
import com.aishtech.poslite.core.session.UnsyncedCounter
import com.aishtech.poslite.data.repository.AuthRepository
import com.aishtech.poslite.data.repository.DeviceActivationRepository
import com.aishtech.poslite.feature.settings.AppBuildInfo
import com.aishtech.poslite.feature.settings.SettingsSnapshot
import com.aishtech.poslite.feature.settings.SettingsViewModel
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import retrofit2.Response

/**
 * UIX-8C-07 — Settings truthfulness + the logout gate (UIX8C-R230/R242/R245). An
 * unknown value renders "Tidak tersedia"; logout is blocked while un-acked
 * transactions exist and allowed only when the queue is drained.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class SettingsViewModelTest {

    @get:Rule val instant = InstantTaskExecutorRule()
    @get:Rule val mainDispatcher = MainDispatcherRule()

    private class FakeToken : TokenStore {
        private var t: String? = "tok"
        override fun saveToken(token: String) { t = token }
        override fun getToken(): String? = t
        override fun clearToken() { t = null }
        override fun isLoggedIn(): Boolean = !t.isNullOrBlank()
    }

    private class FakeCounter(val p: Int, val f: Int) : UnsyncedCounter {
        override suspend fun pendingCount(): Int = p
        override suspend fun failedCount(): Int = f
    }

    private class OfflineMonitor : NetworkMonitor {
        override fun isOnline(): Boolean = false
    }

    private class LogoutApi : NoopPosApiService() {
        var logoutCalls = 0
        override suspend fun logout(): Response<Unit> { logoutCalls++; return Response.success(Unit) }
    }

    private val appInfo = AppBuildInfo(
        versionName = "0.1.0", versionCode = 1L, buildType = "debug",
        packageName = "com.aishtech.poslite", androidRelease = "Android 14",
        deviceModel = "Pixel", installationIdShort = "abcd1234",
    )

    private fun vm(counter: UnsyncedCounter, api: NoopPosApiService = LogoutApi()): Pair<SettingsViewModel, SessionManager> {
        val session = SessionManager(FakeToken())
        val authRepo = AuthRepository(api, session)
        val deviceRepo = DeviceActivationRepository(api, { "u" }, { "i" }, { "d" }, "0.1.0")
        val settings = SettingsViewModel(
            authRepo = authRepo,
            deviceRepo = deviceRepo,
            logoutGuard = LogoutGuard(counter),
            cleaner = LocalDataCleaner(emptyList()),
            session = session,
            unsyncedCounter = counter,
            networkMonitor = OfflineMonitor(),
            appBuildInfo = appInfo,
        )
        return settings to session
    }

    @Test
    fun `logout blocked while un-acked transactions exist`() = runTest {
        val (settings, _) = vm(FakeCounter(2, 1))
        settings.attemptLogout()
        val outcome = settings.logout.value
        assertTrue(outcome is SettingsViewModel.LogoutOutcome.Blocked)
        outcome as SettingsViewModel.LogoutOutcome.Blocked
        assertEquals(2, outcome.pending)
        assertEquals(1, outcome.failed)
    }

    @Test
    fun `logout allowed and performed when queue is drained`() = runTest {
        val api = LogoutApi()
        val (settings, session) = vm(FakeCounter(0, 0), api)
        settings.attemptLogout()
        assertEquals(SettingsViewModel.LogoutOutcome.LoggedOut, settings.logout.value)
        assertEquals(1, api.logoutCalls)
        assertTrue("session ended", !session.isLoggedIn())
    }

    @Test
    fun `refresh renders truthful unavailable values when identity cannot be resolved (offline)`() = runTest {
        val (settings, _) = vm(FakeCounter(0, 0))
        settings.refresh()
        val snap = settings.snapshot.value!!
        assertEquals(SettingsSnapshot.UNAVAILABLE, snap.tenantName)
        assertEquals(SettingsSnapshot.UNAVAILABLE, snap.cashierName)
        assertEquals("0.1.0", snap.appVersionName)
        // Status labels are always present (never colour-alone).
        assertTrue(snap.connection.label.isNotBlank())
        assertTrue(snap.session.label.isNotBlank())
    }
}
