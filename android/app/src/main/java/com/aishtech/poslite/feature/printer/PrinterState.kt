package com.aishtech.poslite.feature.printer

/**
 * UIX-8C-06 — the distinct, typed printer failure reasons (UIX8C-R197). Each is
 * semantically separate so the receipt surface can present a truthful, actionable
 * message and a safe next action instead of a single opaque error string. A raw
 * exception payload is never exposed to the user (UIX8C-R161/R199).
 */
enum class PrinterFailure {
    /** BLUETOOTH_CONNECT has not been granted yet (API 31+); the caller/UI may request it. */
    PERMISSION_REQUIRED,

    /** The user explicitly denied the Bluetooth permission (SecurityException path). */
    PERMISSION_DENIED,

    /** No Bluetooth adapter on this device. */
    UNSUPPORTED,

    /** The Bluetooth adapter is turned off. */
    ADAPTER_DISABLED,

    /** No printer MAC is configured in settings. */
    DEVICE_NOT_CONFIGURED,

    /** The configured printer address is invalid or the paired device is unreachable. */
    DEVICE_UNAVAILABLE,

    /** The RFCOMM connection could not be established. */
    CONNECTION_FAILED,

    /** The connection or write did not complete within the bounded timeout. */
    TIMEOUT,

    /** The connection succeeded but streaming the ESC/POS bytes failed. */
    WRITE_FAILED,

    /** The print was interrupted mid-flight (e.g. the coroutine was cancelled). */
    INTERRUPTED,

    /** A backend-approved receipt is required before printing (not printable). */
    NOT_PRINTABLE,

    /** Any other unexpected failure — surfaced safely, never a crash (UIX8C-R197). */
    UNKNOWN_SAFE_FAILURE,
}

/**
 * The typed outcome of a print/reprint operation, surfaced by [PrinterCoordinator]
 * to the receipt ViewModel. Printing is a presentation operation: none of these
 * outcomes ever changes transaction authority (UIX8C-R191/R192).
 */
sealed class PrintOutcome {
    /** The receipt was streamed to the printer. */
    data object Printed : PrintOutcome()

    /** A print job for this coordinator was already active; the tap was ignored (UIX8C-R198). */
    data object AlreadyPrinting : PrintOutcome()

    /** A typed, actionable failure. [retryable] guides whether a safe reprint is offered. */
    data class Failed(
        val reason: PrinterFailure,
        val message: String,
        val retryable: Boolean,
    ) : PrintOutcome()
}
