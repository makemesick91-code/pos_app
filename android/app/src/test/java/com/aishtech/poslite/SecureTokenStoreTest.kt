package com.aishtech.poslite

import com.aishtech.poslite.core.session.SecureCipher
import com.aishtech.poslite.core.session.SecureKeyValueStore
import com.aishtech.poslite.core.session.SecureTokenStore
import com.aishtech.poslite.core.session.TokenStore
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-07 — the Keystore-backed token store logic (UIX8C-R219). Verified with a
 * fake cipher + in-memory store: the token is never written in plaintext, the
 * legacy plain-prefs token is migrated forward once and its plaintext deleted, a
 * corrupt blob is discarded, and the installation id is stable.
 */
class SecureTokenStoreTest {

    /** Reversible fake cipher: proves the stored blob is not the plaintext. */
    private class FakeCipher : SecureCipher {
        override fun encrypt(plain: String): String = "enc(" + plain + ")"
        override fun decrypt(blob: String): String? =
            if (blob.startsWith("enc(") && blob.endsWith(")")) blob.removePrefix("enc(").removeSuffix(")") else null
    }

    private class InMemoryStore : SecureKeyValueStore {
        val map = mutableMapOf<String, String>()
        override fun read(key: String): String? = map[key]
        override fun write(key: String, value: String) { map[key] = value }
        override fun clear(key: String) { map.remove(key) }
    }

    private class FakeLegacy(private var stored: String?) : TokenStore {
        override fun saveToken(token: String) { stored = token }
        override fun getToken(): String? = stored
        override fun clearToken() { stored = null }
        override fun isLoggedIn(): Boolean = !stored.isNullOrBlank()
    }

    @Test
    fun `token round-trips and is stored only as ciphertext`() {
        val backing = InMemoryStore()
        val store = SecureTokenStore(backing, FakeCipher())
        store.saveToken("sanctum-abc")
        assertEquals("sanctum-abc", store.getToken())
        // The raw token is never written in plaintext — only the ciphertext blob.
        assertFalse(backing.map.values.contains("sanctum-abc"))
        assertTrue(backing.map.values.first().startsWith("enc("))
    }

    @Test
    fun `legacy plaintext token is migrated once then its plaintext is cleared`() {
        val backing = InMemoryStore()
        val legacy = FakeLegacy("legacy-token")
        val store = SecureTokenStore(backing, FakeCipher(), legacy)

        assertEquals("legacy-token", store.getToken())      // migrates
        assertNull(legacy.getToken())                        // plaintext removed
        assertTrue(backing.map.isNotEmpty())                 // now secured
        assertEquals("legacy-token", store.getToken())       // still readable securely
    }

    @Test
    fun `corrupt blob is discarded and reported logged-out`() {
        val backing = InMemoryStore().apply { write("secure_auth_token", "not-a-valid-blob") }
        val store = SecureTokenStore(backing, FakeCipher())
        assertNull(store.getToken())
        assertFalse(store.isLoggedIn())
    }

    @Test
    fun `clear removes secure and legacy tokens`() {
        val backing = InMemoryStore()
        val legacy = FakeLegacy("legacy")
        val store = SecureTokenStore(backing, FakeCipher(), legacy)
        store.saveToken("t")
        store.clearToken()
        assertNull(store.getToken())
        assertNull(legacy.getToken())
    }

    @Test
    fun `installation id is generated once and stays stable`() {
        val backing = InMemoryStore()
        var counter = 0
        val store = SecureTokenStore(backing, FakeCipher(), installationIdGenerator = { "install-${counter++}" })
        val first = store.getOrCreateInstallationId()
        val second = store.getOrCreateInstallationId()
        assertEquals(first, second)
        assertEquals("install-0", first)
    }
}
