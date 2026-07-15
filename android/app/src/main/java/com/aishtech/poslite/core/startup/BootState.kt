package com.aishtech.poslite.core.startup

/**
 * UIX-8C-07 — the single deterministic startup/authentication state
 * (UIX8C-R211/R213). Navigation decisions are never scattered across activities,
 * fragments, interceptors, or workers; they are all derived from this one type,
 * computed by [StartupCoordinator]. Transient states are emitted by the startup
 * ViewModel while it performs work; the terminal routing states are computed by
 * the coordinator from the gathered facts.
 */
sealed interface BootState {

    /** Marker for the states shown as a progress step (bounded, UIX8C-R215). */
    sealed interface Progress : BootState

    /** Cold start; nothing checked yet. */
    data object Bootstrapping : Progress

    /** Room migration / local store readiness in progress. */
    data object DatabaseMigration : Progress

    /** Restoring the secure runtime context from disk. */
    data object RestoringRuntime : Progress

    /** Contacting the server to validate device + session. */
    data object Authenticating : Progress

    /** Performing device activation (user submitted a code). */
    data object ActivatingDevice : Progress

    /** No usable device activation — the activation screen is required. */
    data object ActivationRequired : BootState

    /** No stored session — the login screen is required. */
    data object LoginRequired : BootState

    /** Everything valid, server reachable — the cashier is ready. */
    data object Ready : BootState

    /** Everything valid but the server was unreachable — offline continuation. */
    data object OfflineReady : BootState

    /** A stored session was rejected (401). Re-authentication required; pending
     *  transactions are preserved (UIX8C-R233). */
    data object SessionExpired : BootState

    /** The device is no longer valid/registered on the server (fail closed). */
    data object DeviceInvalid : BootState

    /** The device was revoked by the server (fail closed, no bypass). */
    data class DeviceRevoked(val reason: String?) : BootState

    /** Restored/served identity does not match the bound tenant/outlet
     *  (fail closed, UIX8C-R226). */
    data object ContextMismatch : BootState

    /** A recoverable inconsistency the user can resolve (e.g. re-login/re-activate). */
    data object RecoveryRequired : BootState

    /** A transient failure with a retry path (e.g. DB open failure). */
    data class RecoverableFailure(val message: String) : BootState

    /** An unrecoverable failure. */
    data class FatalFailure(val message: String) : BootState
}
