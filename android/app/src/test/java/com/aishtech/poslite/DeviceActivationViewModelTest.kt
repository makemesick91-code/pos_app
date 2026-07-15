package com.aishtech.poslite

import androidx.arch.core.executor.testing.InstantTaskExecutorRule
import com.aishtech.poslite.core.runtime.ActivationStateStore
import com.aishtech.poslite.data.remote.dto.ActivateDeviceRequestDto
import com.aishtech.poslite.data.remote.dto.DeviceActivationDto
import com.aishtech.poslite.data.remote.dto.DeviceActivationResponseDto
import com.aishtech.poslite.data.repository.DeviceActivationRepository
import com.aishtech.poslite.feature.activation.DeviceActivationViewModel
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.runTest
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import retrofit2.Response

/**
 * UIX-8C-07 — device activation submission (UIX8C-R217). A valid code activates
 * and marks the installation activated ONLY on server success; a short code is
 * rejected locally; the ViewModel-level double-submit guard admits one attempt.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class DeviceActivationViewModelTest {

    @get:Rule val instant = InstantTaskExecutorRule()
    @get:Rule val mainDispatcher = MainDispatcherRule()

    private class FakeActivationState : ActivationStateStore {
        var activated = false
        override fun isActivated(): Boolean = activated
        override fun markActivated() { activated = true }
        override fun clear() { activated = false }
    }

    private class CountingApi(private val ok: Boolean, private val code: Int = 200) : NoopPosApiService() {
        var calls = 0
        override suspend fun activateDevice(request: ActivateDeviceRequestDto): Response<DeviceActivationResponseDto> {
            calls++
            return if (ok) {
                Response.success(
                    DeviceActivationResponseDto(
                        DeviceActivationDto(1L, "activated", 1L, "Kasir", null, null),
                    ),
                )
            } else {
                Response.error(code, "{}".toResponseBody("application/json".toMediaType()))
            }
        }
    }

    private fun repo(api: NoopPosApiService) = DeviceActivationRepository(
        api = api,
        deviceUuidProvider = { "uuid-1" },
        installationIdProvider = { "install-1" },
        deviceLabelProvider = { "Kasir" },
        appVersionName = "0.1.0",
    )

    @Test
    fun `valid code activates and marks activated`() = runTest {
        val state = FakeActivationState()
        val vm = DeviceActivationViewModel(repo(CountingApi(ok = true)), state)
        vm.activate("valid-code-123")
        assertTrue(vm.state.value is DeviceActivationViewModel.State.Activated)
        assertTrue(state.activated)
    }

    @Test
    fun `short code is rejected locally without calling the server`() = runTest {
        val api = CountingApi(ok = true)
        val vm = DeviceActivationViewModel(repo(api), FakeActivationState())
        vm.activate("123")
        assertTrue(vm.state.value is DeviceActivationViewModel.State.Rejected)
        assertEquals(0, api.calls)
    }

    @Test
    fun `server rejection surfaces a message and does not mark activated`() = runTest {
        val state = FakeActivationState()
        val vm = DeviceActivationViewModel(repo(CountingApi(ok = false, code = 403)), state)
        vm.activate("valid-code-123")
        assertTrue(vm.state.value is DeviceActivationViewModel.State.Rejected)
        assertFalse(state.activated)
    }

    /** An API whose activation suspends until [release] completes, so we can hold
     *  a request in flight and prove the guard blocks a concurrent second submit. */
    private class GatedApi : NoopPosApiService() {
        val release = kotlinx.coroutines.CompletableDeferred<Unit>()
        var calls = 0
        override suspend fun activateDevice(request: ActivateDeviceRequestDto): Response<DeviceActivationResponseDto> {
            calls++
            release.await()
            return Response.success(
                DeviceActivationResponseDto(DeviceActivationDto(1L, "activated", 1L, "Kasir", null, null)),
            )
        }
    }

    @Test
    fun `double submit guard blocks a concurrent second submit while one is in flight`() = runTest {
        val api = GatedApi()
        val vm = DeviceActivationViewModel(repo(api), FakeActivationState())

        vm.activate("valid-code-123") // starts; suspends inside the gated API
        assertTrue(vm.state.value is DeviceActivationViewModel.State.Submitting)
        vm.activate("valid-code-123") // second rapid tap — must be ignored by the guard

        api.release.complete(Unit)     // let the first (only) request finish
        assertEquals(1, api.calls)
        assertTrue(vm.state.value is DeviceActivationViewModel.State.Activated)
    }
}
