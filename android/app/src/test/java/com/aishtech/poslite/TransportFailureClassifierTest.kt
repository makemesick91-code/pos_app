package com.aishtech.poslite

import com.aishtech.poslite.core.network.TransportFailureClassifier
import com.aishtech.poslite.core.network.TransportFailureClassifier.Classification
import org.junit.Assert.assertTrue
import org.junit.Test
import java.io.IOException
import java.io.InterruptedIOException
import java.net.ConnectException
import java.net.NoRouteToHostException
import java.net.PortUnreachableException
import java.net.SocketException
import java.net.SocketTimeoutException
import java.net.UnknownHostException
import javax.net.ssl.SSLHandshakeException
import javax.net.ssl.SSLPeerUnverifiedException

/**
 * UIX-8C-04 (UIX8C-R098..R103) — the typed classifier is the single gate that
 * decides whether a checkout failure is eligible for durable offline CASH
 * fallback. It must be eligible ONLY for genuine transport/unavailability
 * failures and fail-closed (Ineligible) for security and unknown errors.
 */
class TransportFailureClassifierTest {

    private fun eligible(t: Throwable) =
        assertTrue("expected Eligible for ${t::class.simpleName}",
            TransportFailureClassifier.classify(t) is Classification.Eligible)

    private fun ineligible(t: Throwable) =
        assertTrue("expected Ineligible for ${t::class.simpleName}",
            TransportFailureClassifier.classify(t) is Classification.Ineligible)

    // --- Eligible transport / temporary-unavailability failures.

    @Test fun `unknown host (dns) is eligible`() = eligible(UnknownHostException("aishpos.online"))
    @Test fun `socket timeout is eligible`() = eligible(SocketTimeoutException("timeout"))
    @Test fun `connect refused is eligible`() = eligible(ConnectException("refused"))
    @Test fun `no route to host is eligible`() = eligible(NoRouteToHostException("no route"))
    @Test fun `port unreachable is eligible`() = eligible(PortUnreachableException("unreachable"))
    @Test fun `socket exception (reset) is eligible`() = eligible(SocketException("connection reset"))
    @Test fun `interrupted io is eligible`() = eligible(InterruptedIOException("interrupted"))

    // --- Ineligible: TLS / certificate / trust failures are SECURITY errors.

    @Test fun `tls handshake failure is ineligible`() = ineligible(SSLHandshakeException("bad cert"))
    @Test fun `tls peer unverified is ineligible`() = ineligible(SSLPeerUnverifiedException("hostname"))

    // --- Ineligible: unknown / programming / serialization / data errors.

    @Test fun `generic io exception is ineligible (fail-closed)`() = ineligible(IOException("canceled"))
    @Test fun `illegal state (programming) is ineligible`() = ineligible(IllegalStateException("boom"))
    @Test fun `null pointer is ineligible`() = ineligible(NullPointerException())
    @Test fun `serialization-style runtime error is ineligible`() =
        ineligible(RuntimeException("JSON EOF at path \$.data"))

    // --- Cause-chain walking: Retrofit/OkHttp wrap the real socket exception.

    @Test fun `wrapped unknown host in cause chain is eligible`() =
        eligible(RuntimeException("request failed", UnknownHostException("dns")))

    @Test fun `wrapped tls failure in cause chain stays ineligible`() =
        ineligible(IOException("io", SSLHandshakeException("cert")))

    @Test fun `null throwable is ineligible`() = ineligible(Throwable("unknown"))
}
