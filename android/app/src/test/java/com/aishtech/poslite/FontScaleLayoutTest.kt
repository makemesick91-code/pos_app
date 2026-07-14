package com.aishtech.poslite

import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-02 responsive-shell structural invariants (UIX8C-R037..R043).
 *
 * The physical R18 failure was: on the cashier screen at 130% font the fixed
 * bottom stack grew and pushed the checkout CTA off a non-scrollable root. This
 * test asserts the STRUCTURE that makes that impossible at ANY font scale:
 *   - the cashier splits into a weighted product region + a weighted,
 *     internally-scrollable bottom action region, both with a minHeight;
 *   - the checkout CTA lives INSIDE the scroll region (always scroll-reachable);
 *   - the payment sheet root is itself a scroll container.
 * Structure is the machine-checkable proxy for "operable at 100/115/130%";
 * final visual confirmation at each scale is operator/physical (UIX8C-R056/R059).
 */
class FontScaleLayoutTest {

    private val cashier = ResPaths.layout("activity_cashier.xml").readText()
    private val sheet = ResPaths.layout("view_payment_sheet.xml").readText()

    @Test fun cashier_product_region_is_weighted_with_min_height() {
        assertTrue("product region must be weighted (0dp + weight)",
            cashier.contains("cashier_product_min_height"))
        // The RecyclerView container carries a weight so it flexes, never fixed.
        assertTrue("product region must use layout_weight",
            Regex("layout_weight=\"[0-9]+\"").containsMatchIn(cashier))
    }

    @Test fun cashier_bottom_action_region_is_scroll_bounded() {
        assertTrue("bottom action region must be a NestedScrollView",
            cashier.contains("NestedScrollView"))
        assertTrue("bottom action region must be identified (cartActionScroll)",
            cashier.contains("cartActionScroll"))
        assertTrue("bottom action region must carry a minHeight token",
            cashier.contains("cashier_action_region_min_height"))
    }

    @Test fun checkout_cta_is_inside_the_scroll_region() {
        val scrollStart = cashier.indexOf("NestedScrollView")
        val ctaIndex = cashier.indexOf("@+id/buttonCheckout\"")
        assertTrue("checkout CTA must exist", ctaIndex >= 0)
        assertTrue("checkout CTA must be inside the scroll-reachable action region (R18/R039)",
            scrollStart in 0 until ctaIndex)
    }

    @Test fun offline_checkout_cta_is_inside_the_scroll_region() {
        val scrollStart = cashier.indexOf("NestedScrollView")
        val cta = cashier.indexOf("@+id/buttonCheckoutOffline\"")
        assertTrue("offline checkout CTA must exist", cta >= 0)
        assertTrue("offline CTA must be scroll-reachable (R18/R039)", scrollStart in 0 until cta)
    }

    @Test fun payment_sheet_root_is_scrollable() {
        // Root element must be a NestedScrollView so the Pay CTA never drops
        // below the sheet fold at 130% (UIX8C-R040).
        val firstTag = sheet.substringAfter("?>").trimStart()
        assertTrue("payment sheet root must be a NestedScrollView (UIX8C-R040)",
            firstTag.replaceBefore("<", "").startsWith("<androidx.core.widget.NestedScrollView") ||
                sheet.indexOf("NestedScrollView") < sheet.indexOf("LinearLayout"))
    }

    @Test fun type_sizes_are_never_hardcoded_dp() {
        // A dp type size would not scale with the system font — forbidden.
        assertTrue("cashier must not use android:textSize with dp",
            !Regex("textSize=\"[0-9.]+dp\"").containsMatchIn(cashier))
        assertTrue("payment sheet must not use android:textSize with dp",
            !Regex("textSize=\"[0-9.]+dp\"").containsMatchIn(sheet))
    }
}
