package com.aishtech.poslite

import com.aishtech.poslite.core.device.DeviceIdentityStorage
import com.aishtech.poslite.core.device.DeviceIdentityStore
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Test

/**
 * Sprint 10 — the device identity is generated once and persisted. These tests
 * use an in-memory storage so the generate-once logic is verified without an
 * Android context.
 */
class DeviceIdentityStoreTest {

    private class InMemoryStorage : DeviceIdentityStorage {
        val map = mutableMapOf<String, String>()
        override fun read(key: String): String? = map[key]
        override fun write(key: String, value: String) { map[key] = value }
        override fun clear(key: String) { map.remove(key) }
    }

    @Test
    fun `uuid is generated once and stable across calls`() {
        var counter = 0
        val store = DeviceIdentityStore(InMemoryStorage(), uuidGenerator = { "uuid-${counter++}" })

        val first = store.getOrCreateDeviceUuid()
        val second = store.getOrCreateDeviceUuid()

        assertEquals("uuid-0", first)
        assertEquals(first, second)
    }

    @Test
    fun `currentDeviceUuid is null before creation and set afterwards`() {
        val store = DeviceIdentityStore(InMemoryStorage(), uuidGenerator = { "generated" })

        assertNull(store.currentDeviceUuid())
        store.getOrCreateDeviceUuid()
        assertNotNull(store.currentDeviceUuid())
        assertEquals("generated", store.currentDeviceUuid())
    }

    @Test
    fun `clearing the store regenerates a fresh uuid safely`() {
        var counter = 0
        val store = DeviceIdentityStore(InMemoryStorage(), uuidGenerator = { "uuid-${counter++}" })

        val first = store.getOrCreateDeviceUuid()
        store.clear()
        assertNull(store.currentDeviceUuid())
        val second = store.getOrCreateDeviceUuid()

        assertEquals("uuid-0", first)
        assertEquals("uuid-1", second)
    }
}
