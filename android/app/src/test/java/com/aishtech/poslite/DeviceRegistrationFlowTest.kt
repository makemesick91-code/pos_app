package com.aishtech.poslite

import com.aishtech.poslite.core.device.DeviceIdentityStorage
import com.aishtech.poslite.core.device.DeviceIdentityStore
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.DeviceMetaDto
import com.aishtech.poslite.data.remote.dto.RegisterDeviceRequestDto
import com.aishtech.poslite.data.remote.dto.RegisteredDeviceDto
import com.aishtech.poslite.data.remote.dto.RegisteredDeviceResponseDto
import com.aishtech.poslite.data.repository.DeviceRepository
import kotlinx.coroutines.test.runTest
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test
import retrofit2.Response

/**
 * Sprint 10 — DeviceRepository ensures a local UUID exists and reflects the
 * backend result. A device-limit rejection (403) surfaces as a blocked Error,
 * never a fake success.
 */
class DeviceRegistrationFlowTest {

    private class InMemoryStorage : DeviceIdentityStorage {
        val map = mutableMapOf<String, String>()
        override fun read(key: String): String? = map[key]
        override fun write(key: String, value: String) { map[key] = value }
        override fun clear(key: String) { map.remove(key) }
    }

    private class FakeApi(
        private val registerResponse: Response<RegisteredDeviceResponseDto>? = null,
    ) : NoopPosApiService() {
        var registerBody: RegisterDeviceRequestDto? = null

        override suspend fun registerDevice(
            request: RegisterDeviceRequestDto,
        ): Response<RegisteredDeviceResponseDto> {
            registerBody = request
            return registerResponse!!
        }
    }

    private fun store() = DeviceIdentityStore(InMemoryStorage(), uuidGenerator = { "uuid-fixed" })

    @Test
    fun `register generates a uuid and returns the backend device`() = runTest {
        val api = FakeApi(
            Response.success(
                RegisteredDeviceResponseDto(
                    data = RegisteredDeviceDto(
                        id = 7,
                        deviceUuid = "uuid-fixed",
                        deviceName = "Test",
                        platform = "ANDROID",
                        status = "ACTIVE",
                        registeredAt = null,
                        lastSeenAt = null,
                    ),
                    meta = DeviceMetaDto(existingDevice = false),
                ),
            ),
        )
        val repo = DeviceRepository(api, store())

        val result = repo.registerCurrentDevice(storeId = 1L)

        result as ResultState.Success
        assertEquals(7L, result.data.device.id)
        assertEquals(false, result.data.existingDevice)
        assertNotNull(api.registerBody)
        assertEquals("uuid-fixed", api.registerBody?.deviceUuid)
        assertEquals("ANDROID", api.registerBody?.platform)
    }

    @Test
    fun `device limit rejection surfaces as an error`() = runTest {
        val api = FakeApi(
            Response.error(
                403,
                "{\"code\":\"DEVICE_LIMIT_REACHED\"}".toResponseBody("application/json".toMediaType()),
            ),
        )
        val repo = DeviceRepository(api, store())

        val result = repo.registerCurrentDevice()

        assertTrue(result is ResultState.Error)
    }

    @Test
    fun `heartbeat without a local uuid returns an error`() = runTest {
        val repo = DeviceRepository(NoopPosApiService(), store())

        // No getOrCreate was called, so currentDeviceUuid is null.
        val result = repo.heartbeat()

        assertTrue(result is ResultState.Error)
    }
}
