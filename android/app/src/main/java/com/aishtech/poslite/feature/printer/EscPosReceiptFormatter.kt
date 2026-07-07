package com.aishtech.poslite.feature.printer

import com.aishtech.poslite.data.remote.dto.ReceiptDto
import java.util.Locale

/**
 * Converts a backend-approved [ReceiptDto] into an ESC/POS byte stream (and a
 * human-readable preview) for a thermal receipt printer.
 *
 * Design constraints (Sprint 6 runtime rule):
 *  - Pure Kotlin, no heavy printer SDK. The output is plain text + a handful of
 *    ESC/POS control bytes so it stays testable on the JVM without a device.
 *  - It only formats what the backend already approved. It never recomputes
 *    totals and never reads a gateway secret (the DTO carries none).
 *  - 58mm baseline (32 cols); 80mm supported via the width parameter (48 cols).
 */
class EscPosReceiptFormatter {

    /** Full ESC/POS payload: init + body + feed + (optional) cut. */
    fun format(receipt: ReceiptDto, paperWidthMm: Int = 58, autoCut: Boolean = true): ByteArray {
        val cols = columnsFor(paperWidthMm)
        val body = buildReceiptText(receipt, cols).toByteArray(Charsets.US_ASCII)

        val out = ArrayList<Byte>(body.size + 16)
        out.addAll(ESC_INIT.toList())
        out.addAll(body.toList())
        out.addAll(FEED_3.toList())
        if (autoCut) out.addAll(CUT_PARTIAL.toList())
        return out.toByteArray()
    }

    /**
     * The printable text body. Exposed (and pure) so it can be unit-tested and
     * previewed on screen without a Bluetooth device.
     */
    fun buildReceiptText(receipt: ReceiptDto, cols: Int = 32): String {
        val sb = StringBuilder()
        val sep = "-".repeat(cols)

        receipt.store?.name?.let { sb.appendLine(center(it, cols)) }
        receipt.store?.code?.let { sb.appendLine(center("(${it})", cols)) }
        receipt.store?.address?.takeIf { it.isNotBlank() }?.let { sb.appendLine(center(it, cols)) }
        sb.appendLine(sep)

        receipt.invoiceNumber?.let { sb.appendLine("No: $it") }
        receipt.saleDate?.let { sb.appendLine("Tgl: $it") }
        receipt.cashier?.name?.let { sb.appendLine("Kasir: $it") }
        sb.appendLine(sep)

        for (item in receipt.items) {
            sb.appendLine(item.productName ?: "-")
            val qtyPrice = "${trimQty(item.qty)} x ${item.unitPrice ?: "0"}"
            sb.appendLine(twoCols(qtyPrice, item.subtotal ?: "0", cols))
        }
        sb.appendLine(sep)

        receipt.totals?.let { t ->
            sb.appendLine(twoCols("Subtotal", t.subtotal ?: "0", cols))
            sb.appendLine(twoCols("Diskon", t.discountTotal ?: "0", cols))
            sb.appendLine(twoCols("Total", t.grandTotal ?: "0", cols))
            sb.appendLine(twoCols("Bayar", t.paidTotal ?: "0", cols))
            sb.appendLine(twoCols("Kembali", t.changeTotal ?: "0", cols))
        }
        sb.appendLine(sep)

        for (p in receipt.payments) {
            val label = listOfNotNull(p.method, p.provider?.takeIf { it != "MANUAL" }).joinToString(" ")
            sb.appendLine(twoCols(label, "${p.amount ?: "0"} (${p.status ?: "-"})", cols))
        }
        sb.appendLine(sep)

        receipt.footer?.let { sb.appendLine(center(it, cols)) }
        return sb.toString()
    }

    private fun columnsFor(paperWidthMm: Int): Int = if (paperWidthMm >= 80) 48 else 32

    private fun center(text: String, cols: Int): String {
        val t = if (text.length > cols) text.substring(0, cols) else text
        val pad = (cols - t.length) / 2
        return " ".repeat(pad.coerceAtLeast(0)) + t
    }

    private fun twoCols(left: String, right: String, cols: Int): String {
        val space = cols - left.length - right.length
        return if (space >= 1) {
            left + " ".repeat(space) + right
        } else {
            // Fall back to two lines when the pair overflows the paper width.
            "$left\n" + right.padStart(cols)
        }
    }

    private fun trimQty(qty: String?): String {
        val value = qty ?: return "0"
        val d = value.toDoubleOrNull() ?: return value
        return if (d % 1.0 == 0.0) d.toLong().toString() else String.format(Locale.US, "%.2f", d)
    }

    private companion object {
        val ESC_INIT = byteArrayOf(0x1B, 0x40) // ESC @  — initialize printer
        val FEED_3 = byteArrayOf(0x0A, 0x0A, 0x0A) // 3 line feeds
        val CUT_PARTIAL = byteArrayOf(0x1D, 0x56, 0x42, 0x00) // GS V 66 0 — feed + partial cut
    }
}
