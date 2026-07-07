package com.aishtech.poslite.core.device

import android.content.Context
import java.util.UUID

/**
 * Supplies the current device UUID without creating one. Consumed by the OkHttp
 * [com.aishtech.poslite.core.network.DeviceHeaderInterceptor] so it can attach
 * the header only once an identity exists.
 */
fun interface DeviceUuidProvider {
    fun currentDeviceUuid(): String?
}

/**
 * Tiny key/value abstraction backing the device identity. Keeping it separate
 * from SharedPreferences makes the generate-once logic unit-testable without an
 * Android context.
 */
interface DeviceIdentityStorage {
    fun read(key: String): String?
    fun write(key: String, value: String)
    fun clear(key: String)
}

/**
 * Sprint 10 — stable, locally generated device identity.
 *
 * A random UUID is generated once on first use and persisted. It carries no user
 * login credential and no payment-gateway secret, and relies on no invasive
 * hardware fingerprint — it is an opaque per-install identifier the backend maps
 * to a RegisteredDevice.
 */
class DeviceIdentityStore(
    private val storage: DeviceIdentityStorage,
    private val uuidGenerator: () -> String = { UUID.randomUUID().toString() },
) : DeviceUuidProvider {

    /** Returns the persisted UUID, generating and storing one on first call. */
    fun getOrCreateDeviceUuid(): String {
        storage.read(KEY_DEVICE_UUID)?.let { existing ->
            if (existing.isNotBlank()) return existing
        }
        val generated = uuidGenerator()
        storage.write(KEY_DEVICE_UUID, generated)
        return generated
    }

    /** Non-creating read for the interceptor: null before the first run. */
    override fun currentDeviceUuid(): String? =
        storage.read(KEY_DEVICE_UUID)?.takeIf { it.isNotBlank() }

    /** Clears the stored identity (a fresh UUID is generated on next use). */
    fun clear() {
        storage.clear(KEY_DEVICE_UUID)
    }

    companion object {
        const val KEY_DEVICE_UUID = "device_uuid"
        private const val PREFS_NAME = "aish_pos_device"

        fun create(context: Context): DeviceIdentityStore =
            DeviceIdentityStore(SharedPrefsDeviceIdentityStorage(context, PREFS_NAME))
    }
}

/** SharedPreferences-backed storage. No credentials are ever written here. */
class SharedPrefsDeviceIdentityStorage(
    context: Context,
    prefsName: String,
) : DeviceIdentityStorage {

    private val prefs = context.applicationContext
        .getSharedPreferences(prefsName, Context.MODE_PRIVATE)

    override fun read(key: String): String? = prefs.getString(key, null)

    override fun write(key: String, value: String) {
        prefs.edit().putString(key, value).apply()
    }

    override fun clear(key: String) {
        prefs.edit().remove(key).apply()
    }
}
