package com.aishtech.poslite.core.session

import android.content.Context
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import java.security.KeyStore
import java.util.UUID
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

/**
 * UIX-8C-07 — Keystore-backed secure storage for the Sanctum bearer token and the
 * app-generated installation id (UIX8C-R218/R219).
 *
 * The value is encrypted with an AES/GCM key held in the hardware-backed
 * `AndroidKeyStore` and never leaves it; only the ciphertext (iv‖ct, base64) is
 * written to SharedPreferences. We deliberately avoid `androidx.security:
 * security-crypto` (deprecated, and a build-reproducibility risk offline) and use
 * the platform Keystore + `javax.crypto` directly (see ADR 0009). The crypto is
 * behind [SecureCipher] so the store logic is unit-testable on the JVM.
 *
 * The legacy plain-SharedPreferences token (Sprint 3) is migrated forward on the
 * first secure read and its plaintext copy is deleted (UIX8C-R219).
 */
interface SecureCipher {
    fun encrypt(plain: String): String

    /** Returns null if the blob cannot be decrypted (key rotated / corrupt). */
    fun decrypt(blob: String): String?
}

/** A tiny key/value abstraction so the store is testable without Android prefs. */
interface SecureKeyValueStore {
    fun read(key: String): String?
    fun write(key: String, value: String)
    fun clear(key: String)
}

class SecureTokenStore(
    private val store: SecureKeyValueStore,
    private val cipher: SecureCipher,
    /** The legacy plaintext token store to migrate away from (nullable in tests). */
    private val legacy: TokenStore? = null,
    private val installationIdGenerator: () -> String = { UUID.randomUUID().toString() },
) : TokenStore {

    override fun saveToken(token: String) {
        store.write(KEY_TOKEN, cipher.encrypt(token))
    }

    override fun getToken(): String? {
        store.read(KEY_TOKEN)?.let { blob ->
            val decrypted = cipher.decrypt(blob)
            if (!decrypted.isNullOrBlank()) return decrypted
            // A corrupt/undecryptable blob is not a valid session — clear it.
            store.clear(KEY_TOKEN)
        }
        // One-time migration from the legacy plaintext token store.
        val legacyToken = legacy?.getToken()
        if (!legacyToken.isNullOrBlank()) {
            saveToken(legacyToken)
            legacy.clearToken()
            return legacyToken
        }
        return null
    }

    override fun clearToken() {
        store.clear(KEY_TOKEN)
        legacy?.clearToken()
    }

    override fun isLoggedIn(): Boolean = !getToken().isNullOrBlank()

    /**
     * The Keystore-backed, app-generated installation id (UIX8C-R218). Generated
     * once, encrypted at rest, never derived from a hardware identifier.
     */
    fun getOrCreateInstallationId(): String {
        store.read(KEY_INSTALLATION)?.let { blob ->
            cipher.decrypt(blob)?.takeIf { it.isNotBlank() }?.let { return it }
        }
        val generated = installationIdGenerator()
        store.write(KEY_INSTALLATION, cipher.encrypt(generated))
        return generated
    }

    companion object {
        private const val KEY_TOKEN = "secure_auth_token"
        private const val KEY_INSTALLATION = "secure_installation_id"

        fun create(context: Context): SecureTokenStore = SecureTokenStore(
            store = SharedPrefsSecureKeyValueStore(context, PREFS_NAME),
            cipher = KeystoreAesGcmCipher(KEY_ALIAS),
            legacy = SharedPrefsTokenStore(context),
        )

        private const val PREFS_NAME = "aish_pos_secure"
        private const val KEY_ALIAS = "aish_pos_token_key"
    }
}

/** SharedPreferences-backed ciphertext store. No plaintext value is written here. */
class SharedPrefsSecureKeyValueStore(
    context: Context,
    prefsName: String,
) : SecureKeyValueStore {

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

/**
 * AES/GCM cipher whose key lives in the hardware-backed AndroidKeyStore. The key
 * is created on first use and never exported. Ciphertext is `base64(iv‖ct)`.
 */
class KeystoreAesGcmCipher(private val keyAlias: String) : SecureCipher {

    override fun encrypt(plain: String): String {
        val cipher = Cipher.getInstance(TRANSFORMATION)
        cipher.init(Cipher.ENCRYPT_MODE, secretKey())
        val iv = cipher.iv
        val ct = cipher.doFinal(plain.toByteArray(Charsets.UTF_8))
        val combined = ByteArray(iv.size + ct.size)
        System.arraycopy(iv, 0, combined, 0, iv.size)
        System.arraycopy(ct, 0, combined, iv.size, ct.size)
        return Base64.encodeToString(combined, Base64.NO_WRAP)
    }

    override fun decrypt(blob: String): String? {
        return try {
            val combined = Base64.decode(blob, Base64.NO_WRAP)
            if (combined.size <= IV_SIZE) return null
            val iv = combined.copyOfRange(0, IV_SIZE)
            val ct = combined.copyOfRange(IV_SIZE, combined.size)
            val cipher = Cipher.getInstance(TRANSFORMATION)
            cipher.init(Cipher.DECRYPT_MODE, secretKey(), GCMParameterSpec(TAG_BITS, iv))
            String(cipher.doFinal(ct), Charsets.UTF_8)
        } catch (_: Exception) {
            null
        }
    }

    private fun secretKey(): SecretKey {
        val keyStore = KeyStore.getInstance(ANDROID_KEYSTORE).apply { load(null) }
        (keyStore.getEntry(keyAlias, null) as? KeyStore.SecretKeyEntry)?.let {
            return it.secretKey
        }
        val generator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, ANDROID_KEYSTORE)
        generator.init(
            KeyGenParameterSpec.Builder(
                keyAlias,
                KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT,
            )
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                .build(),
        )
        return generator.generateKey()
    }

    private companion object {
        const val ANDROID_KEYSTORE = "AndroidKeyStore"
        const val TRANSFORMATION = "AES/GCM/NoPadding"
        const val IV_SIZE = 12
        const val TAG_BITS = 128
    }
}
