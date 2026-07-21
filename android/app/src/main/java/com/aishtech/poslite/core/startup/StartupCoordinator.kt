package com.aishtech.poslite.core.startup

/**
 * UIX-8C-07 — the facts gathered during startup, from which the destination
 * [BootState] is deterministically computed. Every field is a resolved outcome of
 * a check (not an in-progress flag), so the coordinator is a pure function and
 * fully unit-testable (UIX8C-R213).
 */
data class StartupInputs(
    /** Local database / stores opened successfully. */
    val dbReady: Boolean,
    /** A device activation is locally believed present (an activation record/uuid). */
    val activationPresent: Boolean,
    /** The server device-status poll answered this session (connectivity ≠ answered). */
    val deviceStatusReached: Boolean,
    /** Server says the device is revoked (only meaningful when reached). */
    val deviceRevoked: Boolean,
    /** Human-safe revocation reason from the server (may be null). */
    val revocationReason: String?,
    /** Server says the device is active (only meaningful when reached). */
    val deviceActive: Boolean,
    /** A stored session token exists. */
    val hasStoredSession: Boolean,
    /** The session check (`auth/me`) returned a valid identity this session. */
    val sessionValid: Boolean,
    /** The session check returned 401 (expired/rejected). */
    val sessionExpired: Boolean,
    /** Restored/served tenant+outlet matches the bound context (or there is none yet). */
    val tenantConsistent: Boolean,
    /** The authenticated cashier is authorized for the bound tenant/outlet. */
    val cashierAuthorized: Boolean,
    /** Count of durable, not-yet-acknowledged transactions (never lost, UIX8C-R231). */
    val pendingUnsynced: Int,
    /**
     * UIX-8C-08 (DEF-006) — the server previously CONFIRMED this device revoked and
     * that verdict is cached locally. It must fail closed even when the status poll
     * cannot be reached, or offline mode becomes a revocation bypass (UIX8C-R220).
     */
    val deviceRevokedKnownLocally: Boolean = false,
    /** Human-safe reason cached alongside [deviceRevokedKnownLocally]. */
    val knownRevocationReason: String? = null,
    /** A fatal, unrecoverable condition was detected. */
    val fatal: Boolean = false,
    val fatalMessage: String = "",
)

/**
 * The pure, deterministic evaluator (UIX8C-R211/R212). Given the gathered facts it
 * returns exactly one destination [BootState]. The evaluation ORDER encodes the
 * security precedence: a fatal condition, then local readiness, then device trust
 * (activation → revoked → invalid), then tenant consistency, then session, then
 * the cashier authorization — and only then `Ready`/`OfflineReady`.
 *
 * Device revocation is server-authoritative and only trusted when the status poll
 * was actually reached (connectivity ≠ validity, UIX8C-R214); a device that a
 * reached server reports non-active (but not revoked) is `DeviceInvalid`. The app
 * reaches `Ready` only when ALL gates pass (UIX8C-R212).
 */
class StartupCoordinator {

    fun evaluate(i: StartupInputs): BootState {
        if (i.fatal) return BootState.FatalFailure(i.fatalMessage)

        if (!i.dbReady) return BootState.DatabaseMigration

        if (!i.activationPresent) return BootState.ActivationRequired

        // UIX-8C-08 (DEF-006) — a revocation the server ALREADY confirmed is enforced
        // unconditionally, including while the status poll is unreachable. Only the
        // server may declare a revocation, but losing connectivity must never lift one
        // (UIX8C-R220 forbids bypass "via ... process restart, or offline mode").
        if (i.deviceRevokedKnownLocally) {
            return BootState.DeviceRevoked(i.revocationReason ?: i.knownRevocationReason)
        }

        // Device trust is server-authoritative; only act on a poll that answered.
        if (i.deviceStatusReached) {
            if (i.deviceRevoked) return BootState.DeviceRevoked(i.revocationReason)
            if (!i.deviceActive) return BootState.DeviceInvalid
        }

        // Tenant/outlet mismatch fails closed before any session/data use.
        if (!i.tenantConsistent) return BootState.ContextMismatch

        if (!i.hasStoredSession) return BootState.LoginRequired

        if (i.sessionExpired) return BootState.SessionExpired

        if (i.sessionValid) {
            return if (i.cashierAuthorized) BootState.Ready else BootState.ContextMismatch
        }

        // Stored session, server unreachable, not expired: policy-permitted offline
        // continuation (activation present, not revoked, tenant consistent).
        return BootState.OfflineReady
    }
}
