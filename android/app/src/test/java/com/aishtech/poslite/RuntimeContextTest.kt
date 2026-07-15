package com.aishtech.poslite

import com.aishtech.poslite.core.runtime.RuntimeContext
import com.aishtech.poslite.data.remote.dto.MeResponse
import com.aishtech.poslite.data.remote.dto.StoreDto
import com.aishtech.poslite.data.remote.dto.TenantDto
import com.aishtech.poslite.data.remote.dto.UserDto
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-07 — the runtime-context source of truth (UIX8C-R222/R223/R225). It is
 * built only from server-derived identity and rejects an incomplete identity so
 * the app never treats a partial cache as authoritative.
 */
class RuntimeContextTest {

    private fun me(tenantId: Long?, userId: Long?, storeId: Long?, tenantName: String? = "Toko A", userName: String? = "Budi") =
        MeResponse(
            user = userId?.let { UserDto(it, userName, "b@x.id", "cashier", tenantId, storeId) },
            tenant = tenantId?.let { TenantDto(it, tenantName, "active") },
            store = storeId?.let { StoreDto(it, "Outlet 1", "O1") },
            foundation = null,
        )

    private fun build(me: MeResponse?, sessionValid: Boolean = true) = RuntimeContext.fromServer(
        me = me,
        deviceUuid = "uuid-1",
        deviceName = "Kasir",
        installationId = "install-1",
        appVersionName = "0.1.0",
        appVersionCode = 1L,
        buildType = "debug",
        sessionValid = sessionValid,
    )

    @Test
    fun `complete identity builds a validated context`() {
        val ctx = build(me(1L, 10L, 5L))
        assertNotNull(ctx)
        assertTrue(ctx!!.matchesTenant(1L))
        assertFalse(ctx.matchesTenant(2L))
    }

    @Test
    fun `missing tenant yields null (not a partial context)`() {
        assertNull(build(me(null, 10L, 5L)))
    }

    @Test
    fun `missing cashier yields null`() {
        assertNull(build(me(1L, null, 5L)))
    }

    @Test
    fun `blank tenant name yields null`() {
        assertNull(build(me(1L, 10L, 5L, tenantName = "  ")))
    }

    @Test
    fun `same tenant different outlet is not sameTenantOutlet`() {
        val a = build(me(1L, 10L, 5L))!!
        val b = build(me(1L, 11L, 6L))!!
        assertTrue(a.matchesTenant(b.identity.tenantId))
        assertFalse(a.sameTenantOutlet(b))
    }

    @Test
    fun `same tenant same outlet is sameTenantOutlet (cashier switch)`() {
        val a = build(me(1L, 10L, 5L))!!
        val b = build(me(1L, 11L, 5L))!!
        assertTrue(a.sameTenantOutlet(b))
    }
}
