package com.aishtech.poslite.core.network

import android.content.Context
import android.net.ConnectivityManager
import android.net.NetworkCapabilities

/**
 * Lightweight connectivity check (Sprint 7). Deliberately a plain synchronous
 * query — no observers, no heavy dependency — so the QRIS online-only guard and
 * the sync worker can ask "are we online?" cheaply. Kept behind an interface so
 * it can be faked in unit tests.
 */
interface NetworkMonitor {
    fun isOnline(): Boolean
}

class AndroidNetworkMonitor(private val context: Context) : NetworkMonitor {

    override fun isOnline(): Boolean {
        val cm = context.getSystemService(Context.CONNECTIVITY_SERVICE) as? ConnectivityManager
            ?: return false
        val network = cm.activeNetwork ?: return false
        val caps = cm.getNetworkCapabilities(network) ?: return false
        return caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET) &&
            caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)
    }
}
