package com.aishtech.poslite

import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-03 structural invariants for the premium cashier home, catalog, search
 * and category surfaces. Pure-JVM checks over the layout XML — the machine-
 * checkable proxy for the screen contract; final visual/large-font/TalkBack
 * confirmation stays operator/physical (UIX8C-R056/R059).
 */
class CashierCatalogCartLayoutTest {

    private val cashier = ResPaths.layout("activity_cashier.xml").readText()
    private val header = ResPaths.layout("component_cashier_context_header.xml").readText()
    private val chip = ResPaths.layout("item_category_chip.xml").readText()

    // ---- Context header (UIX8C-R061/R062) ----

    @Test fun cashier_includes_canonical_context_header() {
        assertTrue("cashier home must include the context header component",
            cashier.contains("@layout/component_cashier_context_header"))
    }

    @Test fun context_header_shows_business_outlet_cashier_device_and_network() {
        listOf("textContextBusiness", "textContextOutlet", "textContextCashier", "textContextDevice", "chipNetwork")
            .forEach { id -> assertTrue("context header must expose $id", header.contains("@+id/$id")) }
    }

    @Test fun context_header_long_names_ellipsize() {
        // UIX8C-R049/R089 — every context text line truncates, never clips.
        assertTrue("context header must ellipsize long names",
            Regex("ellipsize=\"end\"").findAll(header).count() >= 4)
    }

    @Test fun context_header_network_state_is_not_colour_only() {
        // UIX8C-R047 — the chip carries a text label + an accessible description,
        // not colour alone.
        assertTrue("network chip must have text", header.contains("@string/ctx_network_online"))
        assertTrue("network chip must have an accessible description",
            header.contains("@string/cd_network_status"))
    }

    // ---- Category filter (UIX8C-R074) ----

    @Test fun cashier_has_category_filter_row() {
        assertTrue("cashier home must host the category filter list",
            cashier.contains("@+id/listCategories"))
        assertTrue("category list must be accessible",
            cashier.contains("@string/cd_category_filter"))
    }

    @Test fun category_chip_meets_touch_target_and_ellipsizes() {
        assertTrue("category chip must reach the 48dp touch target (UIX8C-R090)",
            chip.contains("minHeight=\"@dimen/touch_target_min\"") &&
                chip.contains("minWidth=\"@dimen/touch_target_min\""))
        assertTrue("long category names must ellipsize (UIX8C-R049)",
            chip.contains("ellipsize=\"end\""))
        assertTrue("category chip must reuse the canonical chip style",
            chip.contains("@style/Widget.Aish.StatusChip"))
    }

    // ---- Search clear + retry (UIX8C-R069/R075/R090) ----

    @Test fun search_clear_control_is_present_accessible_and_48dp() {
        assertTrue("search must have an explicit clear control",
            cashier.contains("@+id/buttonClearSearch"))
        assertTrue("clear control must be accessible",
            cashier.contains("@string/cd_search_clear"))
        assertTrue("clear control must reach the 48dp touch target",
            cashier.contains("minHeight=\"@dimen/touch_target_min\""))
    }

    @Test fun error_state_has_a_retry_affordance() {
        assertTrue("error state must offer retry (UIX8C-R069)",
            cashier.contains("@+id/buttonRetryProducts"))
        assertTrue("retry must be accessible",
            cashier.contains("@string/cd_retry_products"))
    }

    // ---- Cart/checkout preserved (UIX8C-R085/R086) ----

    @Test fun checkout_cta_still_scroll_reachable_inside_action_region() {
        val scrollStart = cashier.indexOf("NestedScrollView")
        val cta = cashier.indexOf("@+id/buttonCheckout\"")
        assertTrue("checkout CTA must exist", cta >= 0)
        assertTrue("checkout CTA must stay inside the scroll-reachable region (R086)",
            scrollStart in 0 until cta)
    }

    @Test fun clear_cart_confirmation_control_present() {
        assertTrue("clear-cart control must exist", cashier.contains("@+id/buttonClearCart"))
        assertTrue("clear-cart must be accessible", cashier.contains("@string/cd_clear_cart"))
    }

    // ---- No off-system values in the new layouts (UIX8C-R033/R018) ----

    @Test fun new_layouts_have_no_hardcoded_hex() {
        listOf(header, chip).forEach { xml ->
            assertFalse("layout must not contain a hardcoded hex colour",
                Regex("\"#[0-9a-fA-F]{3,8}\"").containsMatchIn(xml))
        }
    }
}
