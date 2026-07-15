package com.aishtech.poslite

import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-05 structural invariants for the premium cash payment sheet
 * (UIX8C-R162/R163/R165/R166). Pure-JVM checks over the layout XML — the
 * machine-checkable proxy for the font-scale / accessibility contract; final
 * visual, 100/115/130% large-font, and TalkBack confirmation stays operator/
 * physical (UIX8C-R166/R169).
 */
class PaymentSheetLayoutTest {

    private val sheet = ResPaths.layout("view_payment_sheet.xml").readText()

    @Test fun root_is_scrollable_so_the_confirm_cta_stays_reachable_at_large_font() {
        // UIX8C-R166 — a NestedScrollView root means the Pay CTA can never drop
        // below the sheet fold at 100/115/130% font; the content scrolls instead.
        assertTrue("payment sheet root must be a NestedScrollView",
            sheet.contains("androidx.core.widget.NestedScrollView"))
    }

    @Test fun confirm_and_quick_controls_meet_the_minimum_touch_target() {
        // UIX8C-R162 — the pay CTA and every tender control carry a governed
        // minHeight token (>=48dp); none is a sub-target tap area.
        assertTrue("pay button must use the pay-height token",
            sheet.contains("@dimen/button_pay_height"))
        assertTrue("secondary/quick controls must use the 48dp touch-target token",
            Regex("@dimen/touch_target_min").findAll(sheet).count() >= 4)
    }

    @Test fun confirm_actions_use_canonical_design_system_widgets() {
        // UIX8C-R131 — the sheet is presentation on the canonical design system,
        // not a bespoke button.
        assertTrue("pay CTA must use Widget.Aish.Button.Pay",
            sheet.contains("@style/Widget.Aish.Button.Pay"))
    }

    @Test fun validation_message_is_an_accessible_live_region_not_colour_only() {
        // UIX8C-R163/R165 — the change/validation line announces via TalkBack and
        // carries an accessible description; status is textual, never colour-only.
        assertTrue("validation/change text must be a polite live region",
            sheet.contains("accessibilityLiveRegion=\"polite\""))
        assertTrue("validation/change text must have an accessible description",
            sheet.contains("@string/cd_payment_state"))
    }

    @Test fun tender_input_has_an_accessible_description() {
        // UIX8C-R163 — the tender field is labelled for assistive tech.
        assertTrue("tender input must have a content description",
            sheet.contains("@string/cd_pay_sheet_tender"))
    }

    @Test fun no_hardcoded_hex_colours_in_the_payment_sheet() {
        // UIX8C-R131/R017 — no raw off-system colour values in the changed UI.
        val hex = Regex("(?:android:\\w+|app:\\w+)=\"#[0-9a-fA-F]{3,8}\"").find(sheet)
        assertFalse("payment sheet must not hardcode hex colours: ${hex?.value}", hex != null)
    }
}
