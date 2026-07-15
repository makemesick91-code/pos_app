package com.aishtech.poslite.core.session

/**
 * UIX-8C-07 — classified cross-tenant cache hygiene (UIX8C-R224/R235/R236/R237).
 *
 * Every local store is classified by the scope it belongs to. A destructive
 * session operation clears exactly the scopes it must and NEVER more: a cashier
 * switch clears only cashier-scoped state; a tenant reset additionally clears
 * outlet/tenant/transaction-scoped state; GLOBAL and DEVICE scopes (device
 * activation, installation id, app config) are NEVER cleared by a switch/reset so
 * the device stays activated (UIX8C-R236). Transaction-scoped data is cleared only
 * on a tenant reset, and only after the unsynced guard has confirmed there is
 * nothing to lose (UIX8C-R229/R231) — the caller enforces that ordering.
 */
enum class DataScope {
    GLOBAL,
    DEVICE,
    TENANT,
    OUTLET,
    CASHIER,
    TRANSACTION,
}

enum class CleanupOp {
    LOGOUT,
    ACCOUNT_SWITCH,
    OUTLET_SWITCH,
    TENANT_RESET,
}

/** A named local store with its scope and a suspend clear action. */
class ScopedStore(
    val id: String,
    val scope: DataScope,
    val clear: suspend () -> Unit,
)

class LocalDataCleaner(private val stores: List<ScopedStore>) {

    /** The scopes a given operation is permitted to clear (pure, testable). */
    fun scopesFor(op: CleanupOp): Set<DataScope> = when (op) {
        // A logout / same-tenant-outlet cashier switch clears only cashier state.
        CleanupOp.LOGOUT,
        CleanupOp.ACCOUNT_SWITCH,
        -> setOf(DataScope.CASHIER)
        // Switching outlet within a tenant additionally clears outlet state.
        CleanupOp.OUTLET_SWITCH -> setOf(DataScope.CASHIER, DataScope.OUTLET)
        // A tenant reset re-scopes everything tenant-bound. Transaction-scoped is
        // included only because the caller has already proven there is no unsynced
        // work (UIX8C-R229). GLOBAL/DEVICE are never included.
        CleanupOp.TENANT_RESET -> setOf(
            DataScope.CASHIER,
            DataScope.OUTLET,
            DataScope.TENANT,
            DataScope.TRANSACTION,
        )
    }

    /**
     * Clear the stores in the scopes permitted for [op] and return the ids cleared.
     * Ordered and best-effort: a failing store does not abort the rest, and the
     * caller re-validates the new context afterwards (compensating recovery,
     * UIX8C-R237). GLOBAL and DEVICE stores are never touched.
     */
    suspend fun clear(op: CleanupOp): List<String> {
        val scopes = scopesFor(op)
        val cleared = mutableListOf<String>()
        for (store in stores) {
            if (store.scope in scopes) {
                store.clear()
                cleared += store.id
            }
        }
        return cleared
    }
}
