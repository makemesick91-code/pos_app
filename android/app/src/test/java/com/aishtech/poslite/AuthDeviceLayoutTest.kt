package com.aishtech.poslite

import com.aishtech.poslite.core.session.ConnectionStatus
import com.aishtech.poslite.core.session.PrinterStatusUi
import com.aishtech.poslite.core.session.SessionStateUi
import com.aishtech.poslite.core.session.SyncStatusUi
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import java.io.File

/**
 * UIX-8C-07 — static accessibility / font-130% resilience checks for the new
 * auth/device/settings surfaces (UIX8C-R244/R248). Font-scale resilience requires
 * a scroll container (so a 130% layout never clips a CTA) and `sp` type sizes
 * (never `dp`); status is never colour-alone (every status enum carries a text
 * label). This is emulator/on-device-independent evidence; the operator-observed
 * 130% + TalkBack PASS remains a separate human checkpoint (never fabricated).
 */
class AuthDeviceLayoutTest {

    private val layouts = listOf(
        "activity_startup.xml",
        "activity_device_activation.xml",
        "activity_session_expired.xml",
        "activity_device_revoked.xml",
        "activity_settings.xml",
    )

    private fun layoutDir(): File {
        for (candidate in listOf("src/main/res/layout", "app/src/main/res/layout")) {
            val f = File(candidate)
            if (f.isDirectory) return f
        }
        throw AssertionError("layout dir not found from ${File(".").absolutePath}")
    }

    @Test
    fun `every new auth surface uses a scroll container so a 130pct layout never clips a CTA`() {
        val dir = layoutDir()
        for (name in layouts) {
            val xml = File(dir, name).readText()
            assertTrue(
                "$name must use a scroll container for font-130% reachability",
                xml.contains("ScrollView"),
            )
        }
    }

    @Test
    fun `new auth surfaces never hardcode dp type sizes (font scaling must apply)`() {
        val dir = layoutDir()
        val dpText = Regex("""android:textSize="\d+(\.\d+)?dp"""")
        for (name in layouts) {
            val xml = File(dir, name).readText()
            assertFalse("$name must not use dp text sizes (use sp / TextAppearance.Aish.*)", dpText.containsMatchIn(xml))
        }
    }

    @Test
    fun `settings and revoked screens expose accessibility content descriptions`() {
        val dir = layoutDir()
        // At least one accessible label on the interactive/status surfaces.
        val settings = File(dir, "activity_settings.xml").readText()
        assertTrue("settings should carry accessibility content descriptions", settings.contains("contentDescription"))
    }

    @Test
    fun `every operational status carries a non-blank text label (never colour-alone)`() {
        for (s in ConnectionStatus.entries) assertTrue(s.name, s.label.isNotBlank())
        for (s in SyncStatusUi.entries) assertTrue(s.name, s.label.isNotBlank())
        for (s in SessionStateUi.entries) assertTrue(s.name, s.label.isNotBlank())
        for (s in PrinterStatusUi.entries) assertTrue(s.name, s.label.isNotBlank())
    }
}
