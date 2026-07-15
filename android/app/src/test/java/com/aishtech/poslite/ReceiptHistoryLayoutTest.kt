package com.aishtech.poslite

import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-06 — structural font-scale / accessibility invariants for the receipt
 * and transaction-history surfaces (UIX8C-R202/R205/R206/R208). Structure is the
 * machine-checkable proxy for "operable at 100/115/130%"; final visual/TalkBack
 * confirmation is operator/physical and deferred to code freeze.
 */
class ReceiptHistoryLayoutTest {

    private val receipt = ResPaths.layout("activity_receipt.xml").readText()
    private val receiptLine = ResPaths.layout("item_receipt_line.xml").readText()
    private val history = ResPaths.layout("activity_transaction_history.xml").readText()
    private val historyItem = ResPaths.layout("item_transaction.xml").readText()

    @Test fun receipt_content_isInsideAScrollContainer() {
        assertTrue("receipt content must scroll so nothing clips at 130% font",
            receipt.contains("NestedScrollView"))
    }

    @Test fun receipt_primaryActions_arePresent_belowScroll() {
        val scrollEnd = receipt.lastIndexOf("NestedScrollView")
        val printIdx = receipt.indexOf("@+id/buttonPrint")
        val newTxIdx = receipt.indexOf("@+id/buttonNewTransaction")
        assertTrue("print action must exist", printIdx >= 0)
        assertTrue("new-transaction action must exist", newTxIdx >= 0)
        // Actions live after the scroll region so they stay pinned and reachable.
        assertTrue("print action must be pinned outside the scroll region", printIdx > scrollEnd)
    }

    @Test fun receipt_actions_meetTouchTargetMinimum() {
        assertTrue("print button must carry the 48dp touch-target token",
            Regex("buttonPrint[\\s\\S]{0,400}touch_target_min").containsMatchIn(receipt))
        assertTrue("new-transaction button must carry the 48dp touch-target token",
            Regex("buttonNewTransaction[\\s\\S]{0,400}touch_target_min").containsMatchIn(receipt))
    }

    @Test fun receipt_hasNoHardcodedDpTextSizes() {
        assertFalse("receipt type sizes must scale with the system font",
            Regex("textSize=\"[0-9.]+dp\"").containsMatchIn(receipt))
        assertFalse(Regex("textSize=\"[0-9.]+dp\"").containsMatchIn(receiptLine))
    }

    @Test fun receipt_hasNoHardcodedHexColours() {
        assertFalse("receipt must use @color tokens, never raw hex",
            Regex("(android:|app:)[A-Za-z]+=\"#").containsMatchIn(receipt))
    }

    @Test fun history_list_isWeightedScrollRegion() {
        assertTrue("history list region must flex (weight), never fixed",
            Regex("layout_weight=\"[0-9]+\"").containsMatchIn(history))
        assertTrue(history.contains("RecyclerView"))
    }

    @Test fun history_row_isAccessibleTouchTarget() {
        assertTrue("history row must be a >=48dp touch target",
            historyItem.contains("touch_target_min"))
        assertTrue("history row must be focusable/clickable for navigation + TalkBack",
            historyItem.contains("android:clickable=\"true\"") &&
                historyItem.contains("android:focusable=\"true\""))
    }

    @Test fun history_hasNoHardcodedDpTextSizes() {
        assertFalse(Regex("textSize=\"[0-9.]+dp\"").containsMatchIn(history))
        assertFalse(Regex("textSize=\"[0-9.]+dp\"").containsMatchIn(historyItem))
    }
}
