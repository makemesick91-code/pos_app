package com.aishtech.poslite

import org.junit.Assert.assertTrue
import org.junit.Test
import java.io.File

/**
 * UIX-8C-02 accessibility invariants (UIX8C-R044..R048).
 *
 * Pure-JVM checks over the layouts: interactive controls meet the 48dp touch
 * target (via a style or an explicit minHeight token), touch-target dimens are
 * >=48dp, key controls carry content descriptions, and status is never conveyed
 * by colour alone (a text label always accompanies a state colour).
 */
class AccessibilityLayoutTest {

    private val values = ResPaths.valuesDir()
    private val layout = ResPaths.layoutDir()

    @Test fun touch_target_dimens_are_at_least_48dp() {
        val d = File(values, "dimens.xml").readText()
        listOf("touch_target_min", "button_height", "input_height", "button_pay_height",
            "stepper_size").forEach { name ->
            val m = Regex("name=\"$name\">([0-9]+)dp").find(d)
            assertTrue("dimen $name must be defined in dp", m != null)
            val v = m!!.groupValues[1].toInt()
            assertTrue("$name must be >= 48dp (got $v)", v >= 48)
        }
    }

    @Test fun interactive_controls_meet_touch_target() {
        layoutFiles().forEach { f ->
            val txt = f.readText()
            openingTags(txt, listOf("<Button", "<EditText")).forEach { tag ->
                val ok = tag.contains("style=") || tag.contains("minHeight")
                assertTrue("interactive control without style/minHeight in ${f.name}: " +
                    tag.take(80), ok)
                // Any explicit fixed dp height must be >= 48 (0dp/wrap are fine).
                Regex("layout_height=\"([0-9]+)dp\"").find(tag)?.let { m ->
                    val v = m.groupValues[1].toInt()
                    assertTrue("fixed height < 48dp on control in ${f.name}", v == 0 || v >= 48)
                }
            }
        }
    }

    @Test fun cashier_and_payment_controls_have_content_descriptions() {
        val cashier = File(layout, "activity_cashier.xml").readText()
        // Ambiguous inputs/CTAs must expose an accessible name.
        listOf("cd_search_products", "cd_paid_amount", "cd_checkout_online",
            "cd_checkout_offline", "cd_clear_cart", "cd_cart_total").forEach {
            assertTrue("cashier missing contentDescription $it", cashier.contains(it))
        }
    }

    @Test fun status_is_text_plus_colour_never_colour_alone() {
        // Offline state component: chip colour + a text label.
        val off = File(layout, "component_state_offline.xml").readText()
        assertTrue("offline state must carry a text label",
            off.contains("textStateOffline"))
        assertTrue("offline state must carry a paired state colour",
            off.contains("state_offline_fg"))
        // History rows carry an explicit textual status (never colour alone).
        val item = File(layout, "item_transaction.xml").readText()
        assertTrue("history row must have a textual status field",
            item.contains("textHistoryStatus"))
    }

    /** Extracts opening tag text (up to the closing "/>" or ">") for the given prefixes. */
    private fun openingTags(txt: String, prefixes: List<String>): List<String> {
        val out = mutableListOf<String>()
        prefixes.forEach { p ->
            var i = txt.indexOf(p)
            while (i >= 0) {
                val end = txt.indexOf('>', i)
                if (end >= 0) out.add(txt.substring(i, end + 1))
                i = txt.indexOf(p, i + 1)
            }
        }
        return out
    }

    private fun layoutFiles(): List<File> =
        layout.listFiles { f -> f.extension == "xml" }?.toList() ?: emptyList()
}
