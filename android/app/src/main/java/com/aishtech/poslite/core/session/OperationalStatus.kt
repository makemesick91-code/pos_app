package com.aishtech.poslite.core.session

/**
 * UIX-8C-07 — truthful operational status (UIX8C-R242/R243/R244). Connection,
 * sync, printer, and session status derive from an ACTUAL source of truth, never
 * "green because a config is saved". Each state carries a human-readable text
 * [label] (Indonesian) so status is never conveyed by colour alone, and a
 * [tone] hint the UI maps to a token colour (paired with the label, never
 * standalone).
 */
enum class StatusTone { POSITIVE, NEUTRAL, WARNING, NEGATIVE }

enum class ConnectionStatus(val label: String, val tone: StatusTone) {
    CONFIGURED("Terkonfigurasi", StatusTone.NEUTRAL),
    CHECKING("Memeriksa…", StatusTone.NEUTRAL),
    CONNECTED("Terhubung", StatusTone.POSITIVE),
    DISCONNECTED("Terputus", StatusTone.WARNING),
    DEGRADED("Terbatas", StatusTone.WARNING),
    UNAVAILABLE("Tidak tersedia", StatusTone.NEUTRAL),
}

enum class SyncStatusUi(val label: String, val tone: StatusTone) {
    SYNCED("Tersinkron", StatusTone.POSITIVE),
    SYNC_PENDING("Menunggu sinkronisasi", StatusTone.WARNING),
    SYNCING("Menyinkronkan…", StatusTone.NEUTRAL),
    SYNC_FAILED("Gagal sinkronisasi", StatusTone.NEGATIVE),
    UNAVAILABLE("Tidak tersedia", StatusTone.NEUTRAL),
}

enum class SessionStateUi(val label: String, val tone: StatusTone) {
    ACTIVE("Sesi aktif", StatusTone.POSITIVE),
    CHECKING("Memeriksa sesi…", StatusTone.NEUTRAL),
    SESSION_EXPIRED("Sesi berakhir", StatusTone.WARNING),
    DEVICE_REVOKED("Perangkat dinonaktifkan", StatusTone.NEGATIVE),
    UNAVAILABLE("Tidak tersedia", StatusTone.NEUTRAL),
}

enum class PrinterStatusUi(val label: String, val tone: StatusTone) {
    CONFIGURED("Terkonfigurasi", StatusTone.NEUTRAL),
    CHECKING("Memeriksa…", StatusTone.NEUTRAL),
    CONNECTED("Terhubung", StatusTone.POSITIVE),
    DISCONNECTED("Terputus", StatusTone.WARNING),
    PERMISSION_REQUIRED("Izin diperlukan", StatusTone.WARNING),
    UNAVAILABLE("Tidak tersedia", StatusTone.NEUTRAL),
}

/**
 * A status chip's presentation: the text label is mandatory (never colour-alone),
 * the tone is an accompanying hint only.
 */
data class StatusChip(val label: String, val tone: StatusTone) {
    companion object {
        fun of(status: ConnectionStatus) = StatusChip(status.label, status.tone)
        fun of(status: SyncStatusUi) = StatusChip(status.label, status.tone)
        fun of(status: SessionStateUi) = StatusChip(status.label, status.tone)
        fun of(status: PrinterStatusUi) = StatusChip(status.label, status.tone)
    }
}
