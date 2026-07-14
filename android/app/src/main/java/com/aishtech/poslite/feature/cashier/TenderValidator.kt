package com.aishtech.poslite.feature.cashier

import com.aishtech.poslite.core.money.RupiahMoney

/**
 * UIX-8C-05 (UIX8C-R136/R137/R139/R140) — the single, pure, deterministic
 * validator for a cashier-entered CASH tender against the canonical amount due.
 *
 * It is presentation logic only: it delegates parsing to [RupiahMoney.parse]
 * (locale-safe, overflow-safe — a value beyond [Long] range parses to null, never
 * a fabricated 0) and every comparison to the canonical whole-rupiah helpers. It
 * NEVER computes money with float/double, NEVER mutates the cart, and NEVER
 * starts a checkout. A caller may submit ONLY when the result is [Result.Valid].
 *
 * Pure and framework-free so it is fully JVM-unit-testable without a device.
 */
object TenderValidator {

    /** The exhaustive outcome of validating a tender string against a due amount. */
    sealed class Result {
        /** No tender entered yet (blank input). Submission is blocked. */
        data object Empty : Result()

        /** Garbage / non-numeric / out-of-range input. Submission is blocked. */
        data object Invalid : Result()

        /** A valid number that does not cover the due amount. Submission is blocked. */
        data class Insufficient(val shortBy: Long) : Result()

        /** A valid tender that fully covers the due amount, with the exact change. */
        data class Valid(val tender: Long, val change: Long) : Result()
    }

    /**
     * Validate [raw] against [amountDue] (whole rupiah). Distinguishes blank
     * (Empty) from garbage/overflow (Invalid) so the UI can message each truthfully.
     * A negative or below-due tender can never be [Result.Valid] (UIX8C-R139/R140).
     */
    fun validate(raw: String?, amountDue: Long): Result {
        val parsed = RupiahMoney.parse(raw)
        if (parsed == null) {
            // parse() returns null for BOTH blank and garbage/overflow; separate
            // them so the operator sees the right message.
            return if (raw.isNullOrBlank()) Result.Empty else Result.Invalid
        }
        // Defensive: parse() never yields a negative, but a negative tender is
        // never valid money (UIX8C-R136/R140).
        if (parsed < 0L) return Result.Invalid
        if (!RupiahMoney.isSufficient(parsed, amountDue)) {
            return Result.Insufficient(shortBy = amountDue - parsed)
        }
        return Result.Valid(tender = parsed, change = RupiahMoney.change(parsed, amountDue))
    }

    /** True only when [result] permits a checkout submission (UIX8C-R139). */
    fun canSubmit(result: Result): Boolean = result is Result.Valid
}
