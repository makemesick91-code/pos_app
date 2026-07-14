package com.aishtech.poslite.core.network

import java.io.InterruptedIOException
import java.net.ConnectException
import java.net.NoRouteToHostException
import java.net.PortUnreachableException
import java.net.SocketException
import java.net.SocketTimeoutException
import java.net.UnknownHostException
import javax.net.ssl.SSLException
import javax.net.ssl.SSLHandshakeException
import javax.net.ssl.SSLKeyException
import javax.net.ssl.SSLPeerUnverifiedException

/**
 * UIX-8C-04 (UIX8C-R098..R103) — typed classification of a checkout failure.
 *
 * The offline CASH durability fix must NEVER convert a canonical rejection or an
 * unsafe error into an "offline success". Only a *governed transport /
 * temporary-unavailability* failure — the backend genuinely could not be reached —
 * is eligible for the durable offline fallback.
 *
 * This classifier is deliberately deterministic, allow-listed, and fail-closed:
 *
 *  - A KNOWN transport failure type (DNS/timeout/connect/route/reset) → [Eligible].
 *  - A TLS certificate/hostname/trust failure → [Ineligible] (a security error,
 *    never an "offline" condition; UIX8C-R103).
 *  - Anything else (serialization, mapping, data-integrity, programming errors,
 *    a bare unclassified IOException) → [Ineligible] (fail-closed; UIX8C-R103).
 *
 * It inspects the whole cause chain because Retrofit/OkHttp frequently wrap the
 * original socket exception. It carries no request/response payload, so a
 * classification reason can never leak a token, credential, or PII (UIX8C-R127).
 *
 * NOTE: this classifier only sees *thrown* failures. A received HTTP response
 * (any status, including 4xx/5xx) proves the server was reachable and is handled
 * separately by the repository as a canonical outcome — it is never routed here
 * and is never eligible for offline fallback (UIX8C-R099..R102).
 */
object TransportFailureClassifier {

    sealed class Classification {
        /**
         * Governed transport/unavailability failure — the request never reached a
         * responding server. Eligible for durable offline CASH fallback.
         */
        data class Eligible(val reason: String) : Classification()

        /**
         * A reachable-but-rejected, security, or programming failure. It must NEVER
         * become an offline success; the cart is preserved and a truthful error is
         * shown.
         */
        data class Ineligible(val reason: String) : Classification()
    }

    /** Classify a thrown checkout failure. Walks the cause chain (bounded). */
    fun classify(throwable: Throwable?): Classification {
        var current: Throwable? = throwable
        var depth = 0
        while (current != null && depth < MAX_CAUSE_DEPTH) {
            classifyDirect(current)?.let { return it }
            current = current.cause?.takeIf { it !== current }
            depth++
        }
        // Fail-closed: an unrecognised failure is NOT eligible for offline fallback.
        return Classification.Ineligible("Kesalahan tidak dikenal — transaksi tidak diantrikan offline.")
    }

    /** Classify a single throwable (no cause walking). Returns null if unknown. */
    private fun classifyDirect(t: Throwable): Classification? = when (t) {
        // --- Security first: a TLS/cert/hostname/trust failure is NEVER "offline".
        // (SSLException is an IOException, so it MUST be matched before the generic
        // socket/transport types below; UIX8C-R103.)
        is SSLPeerUnverifiedException,
        is SSLHandshakeException,
        is SSLKeyException,
        ->
            Classification.Ineligible("Kegagalan validasi TLS/sertifikat — bukan kondisi offline.")

        is SSLException ->
            Classification.Ineligible("Kegagalan keamanan TLS — bukan kondisi offline.")

        // --- Governed transport / temporary-unavailability failures → eligible.
        is UnknownHostException ->
            Classification.Eligible("Alamat server tidak dapat diselesaikan (DNS).")

        is SocketTimeoutException ->
            Classification.Eligible("Waktu koneksi habis.")

        is ConnectException ->
            Classification.Eligible("Koneksi ke server ditolak/tidak tersedia.")

        is NoRouteToHostException ->
            Classification.Eligible("Tidak ada rute ke server.")

        is PortUnreachableException ->
            Classification.Eligible("Port server tidak dapat dijangkau.")

        // SocketException covers connection reset / network unreachable / broken
        // pipe — genuine transport unavailability. (Connect/NoRoute/PortUnreachable
        // above are subclasses and matched first for a precise reason.)
        is SocketException ->
            Classification.Eligible("Koneksi jaringan terputus/tidak tersedia.")

        // A generic interrupted I/O that is not a SocketTimeout is treated as a
        // transport interruption (e.g. OkHttp read/write interrupted mid-flight).
        is InterruptedIOException ->
            Classification.Eligible("Operasi jaringan terputus.")

        else -> null
    }

    private const val MAX_CAUSE_DEPTH = 8
}
