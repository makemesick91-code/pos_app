package com.aishtech.poslite.core.runtime

import com.aishtech.poslite.core.session.SecureKeyValueStore

/**
 * UIX-8C-07 — holds the current validated [RuntimeContext] (the in-memory source
 * of truth for the session) and persists a minimal tenant/outlet hint used to
 * detect a cross-tenant mismatch on the next start (UIX8C-R222/R224/R226).
 *
 * Only the tenant/outlet ids are persisted (never names, tokens, or PII); they
 * exist solely so startup can fail closed when a freshly resolved identity does
 * not match the previously bound tenant (UIX8C-R226). Setting a new context
 * updates the hint; clearing (on tenant reset) removes it.
 */
class RuntimeContextStore(private val store: SecureKeyValueStore) {

    @Volatile
    private var current: RuntimeContext? = null

    fun current(): RuntimeContext? = current

    fun set(context: RuntimeContext) {
        current = context
        store.write(KEY_TENANT, context.identity.tenantId.toString())
        context.identity.outletId?.let { store.write(KEY_OUTLET, it.toString()) }
            ?: store.clear(KEY_OUTLET)
    }

    /** The last bound tenant id, or null if none has been bound on this install. */
    fun lastTenantId(): Long? = store.read(KEY_TENANT)?.toLongOrNull()

    fun lastOutletId(): Long? = store.read(KEY_OUTLET)?.toLongOrNull()

    /**
     * Tenant/outlet consistency for startup: consistent when there is no prior
     * binding yet, or when the resolved tenant matches the bound tenant. A
     * resolved tenant that differs from the bound tenant is a fail-closed mismatch
     * (UIX8C-R226).
     */
    fun isConsistentWith(resolvedTenantId: Long?): Boolean {
        val bound = lastTenantId() ?: return true
        if (resolvedTenantId == null) return true // unresolved is handled elsewhere
        return bound == resolvedTenantId
    }

    fun clearInMemory() {
        current = null
    }

    /** Full clear used on a tenant reset (device stays activated separately). */
    fun clearBinding() {
        current = null
        store.clear(KEY_TENANT)
        store.clear(KEY_OUTLET)
    }

    private companion object {
        const val KEY_TENANT = "runtime_tenant_id"
        const val KEY_OUTLET = "runtime_outlet_id"
    }
}
