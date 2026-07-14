package com.aishtech.poslite

import org.junit.Assert.assertTrue
import org.junit.Test
import java.io.File

/**
 * UIX-8C-02 design-system resource invariants (UIX8C-R031..R034, R047, R050).
 *
 * Pure-JVM regression net over the on-device design system: it reads the
 * res/values and res/layout XML directly and asserts the canonical tokens,
 * component styles, and reusable state components exist and that NO hardcoded
 * off-system value (hex colour, raw dp/sp) leaks into a layout. These are
 * development evidence only (UIX8C-R056) — they never replace physical closure.
 */
class DesignSystemResourceTest {

    private val res: File = ResPaths.valuesDir()
    private val layout: File = ResPaths.layoutDir()

    private fun values(name: String) = File(res, name).readText()

    @Test fun canonical_state_colour_tokens_exist() {
        val c = values("colors.xml")
        listOf(
            "state_online_fg", "state_offline_fg", "state_pending_fg",
            "state_syncing_fg", "state_synced_fg", "state_failed_fg",
            "state_conflict_fg", "state_disabled_fg", "accent_gold",
        ).forEach { assertTrue("missing colour token $it", c.contains("name=\"$it\"")) }
    }

    @Test fun spacing_shape_elevation_tokens_exist() {
        val d = values("dimens.xml")
        listOf(
            "space_2xs", "space_lg_plus", "radius_pill", "elevation_raised",
            "cashier_product_min_height", "cashier_action_region_min_height",
        ).forEach { assertTrue("missing dimen token $it", d.contains("name=\"$it\"")) }
        val s = values("shapes.xml")
        listOf(
            "ShapeAppearance.Aish.SmallComponent", "ShapeAppearance.Aish.Pill",
            "ShapeAppearance.Aish.BottomSheet",
        ).forEach { assertTrue("missing shape token $it", s.contains("name=\"$it\"")) }
    }

    @Test fun canonical_text_roles_and_component_styles_exist() {
        val s = values("styles.xml")
        listOf(
            "TextAppearance.Aish.MoneyTotal", "TextAppearance.Aish.MoneySecondary",
            "TextAppearance.Aish.Status", "TextAppearance.Aish.Receipt",
            "Widget.Aish.Button.Tertiary", "Widget.Aish.Button.Icon",
            "Widget.Aish.EditText", "Widget.Aish.StatusChip",
            "Widget.Aish.SectionHeader", "Widget.Aish.BottomActionRegion",
            "Widget.Aish.StateContainer",
        ).forEach { assertTrue("missing style $it", s.contains("name=\"$it\"")) }
    }

    @Test fun theme_is_material3_with_centralized_shapes() {
        val t = values("themes.xml")
        assertTrue("theme must extend Material 3", t.contains("Theme.Material3"))
        assertTrue("theme must wire shapeAppearanceSmallComponent",
            t.contains("shapeAppearanceSmallComponent"))
    }

    @Test fun reusable_state_components_exist() {
        listOf(
            "component_state_loading", "component_state_empty",
            "component_state_error", "component_state_offline",
            "component_cashier_context_header",
        ).forEach { assertTrue("missing component layout $it", File(layout, "$it.xml").exists()) }
    }

    @Test fun no_hardcoded_hex_colour_in_layouts() {
        val hex = Regex("\"#[0-9A-Fa-f]{3,8}\"")
        layoutFiles().forEach { f ->
            assertTrue("hardcoded hex colour in ${f.name}", !hex.containsMatchIn(f.readText()))
        }
    }

    @Test fun no_raw_dp_or_sp_design_values_in_layouts() {
        // 0dp (weight/constraint) and 1dp (hairline) are the only permitted dp
        // literals; type sizes must be tokenized (system-scalable sp).
        val dp = Regex("\"([0-9]+(?:\\.[0-9]+)?)dp\"")
        val sp = Regex("\"[0-9]+(?:\\.[0-9]+)?sp\"")
        layoutFiles().forEach { f ->
            val txt = f.readText()
            dp.findAll(txt).forEach { m ->
                val v = m.groupValues[1]
                assertTrue("raw dp literal ${m.value} in ${f.name}", v == "0" || v == "1")
            }
            assertTrue("raw sp type size in ${f.name}", !sp.containsMatchIn(txt))
        }
    }

    private fun layoutFiles(): List<File> =
        layout.listFiles { f -> f.extension == "xml" }?.toList() ?: emptyList()
}
