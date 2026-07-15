package com.aishtech.poslite.feature.cashier

import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.core.os.bundleOf
import com.aishtech.poslite.R
import com.aishtech.poslite.core.money.RupiahMoney
import com.aishtech.poslite.databinding.ViewPaymentSheetBinding
import com.google.android.material.bottomsheet.BottomSheetDialogFragment

/**
 * UIX-8B — native cash payment tender sheet (UIX8B-R045..R055).
 *
 * Presentation only: it collects the tendered cash (parsed via
 * [RupiahMoney.parse], never a fabricated 0), computes change with integer-exact
 * arithmetic, and validates sufficiency. It holds NO transaction authority —
 * confirming calls back into the host, which invokes the canonical
 * `CashierViewModel` checkout (double-submit guard, stable `clientReference`, and
 * durable-save-before-cart-clear are all preserved there). QRIS is not offered
 * here (online-only, separate lifecycle).
 */
class PaymentSheetFragment : BottomSheetDialogFragment() {

    /** Implemented by the hosting cashier screen. */
    interface Host {
        fun onCashTender(paidAmount: Long, offline: Boolean)
    }

    private var _binding: ViewPaymentSheetBinding? = null
    private val binding get() = _binding!!
    private var amountDue: Long = 0L

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?,
    ): View {
        _binding = ViewPaymentSheetBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        amountDue = requireArguments().getLong(ARG_DUE)
        binding.textSheetDue.text = RupiahMoney.format(amountDue)

        bindQuickTenders()

        binding.inputSheetTender.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, a: Int, b: Int, c: Int) = Unit
            override fun onTextChanged(s: CharSequence?, a: Int, b: Int, c: Int) = renderChange()
            override fun afterTextChanged(s: Editable?) = Unit
        })

        binding.buttonSheetPayOnline.setOnClickListener { confirm(offline = false) }
        binding.buttonSheetPayOffline.setOnClickListener { confirm(offline = true) }
        renderChange()
    }

    private fun bindQuickTenders() {
        binding.buttonQuickExact.setOnClickListener { setTender(amountDue) }
        val quicks = QuickTenderCalculator.options(amountDue)
        val buttons = listOf(binding.buttonQuick1, binding.buttonQuick2, binding.buttonQuick3)
        buttons.forEachIndexed { i, button ->
            val value = quicks.getOrNull(i)
            if (value == null) {
                button.visibility = View.GONE
            } else {
                button.visibility = View.VISIBLE
                button.text = RupiahMoney.format(value)
                button.setOnClickListener { setTender(value) }
            }
        }
    }

    private fun setTender(amount: Long) {
        binding.inputSheetTender.setText(amount.toString())
        binding.inputSheetTender.setSelection(binding.inputSheetTender.text?.length ?: 0)
    }

    /**
     * UIX8C-R136/R137/R139/R140 — render change + validation through the single
     * pure [TenderValidator]. Empty vs garbage/overflow vs insufficient are
     * distinct, truthful messages; the confirm actions are enabled ONLY for a fully
     * valid tender so an insufficient/invalid amount can never enter checkout.
     */
    private fun renderChange() {
        val result = TenderValidator.validate(binding.inputSheetTender.text?.toString(), amountDue)
        when (result) {
            is TenderValidator.Result.Empty ->
                binding.textSheetChange.text = getString(R.string.pay_sheet_enter_tender)
            is TenderValidator.Result.Invalid ->
                binding.textSheetChange.text = getString(R.string.pay_sheet_invalid_tender)
            is TenderValidator.Result.Insufficient ->
                binding.textSheetChange.text = getString(
                    R.string.pay_sheet_short,
                    RupiahMoney.format(result.shortBy),
                )
            is TenderValidator.Result.Valid ->
                binding.textSheetChange.text = getString(
                    R.string.pay_sheet_change_label,
                    RupiahMoney.format(result.change),
                )
        }
        val canSubmit = TenderValidator.canSubmit(result)
        binding.buttonSheetPayOnline.isEnabled = canSubmit
        binding.buttonSheetPayOffline.isEnabled = canSubmit
    }

    private fun confirm(offline: Boolean) {
        // Re-validate at confirm time so a race or stale click can never submit an
        // invalid tender (UIX8C-R139); the canonical VM guard is the last defense.
        val result = TenderValidator.validate(binding.inputSheetTender.text?.toString(), amountDue)
        val valid = result as? TenderValidator.Result.Valid ?: return
        (activity as? Host)?.onCashTender(valid.tender, offline)
        dismiss()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }

    companion object {
        const val TAG = "PaymentSheetFragment"
        private const val ARG_DUE = "amount_due"

        fun newInstance(amountDue: Long): PaymentSheetFragment =
            PaymentSheetFragment().apply { arguments = bundleOf(ARG_DUE to amountDue) }

        /**
         * Backward-compatible shim: the authoritative quick-tender logic now lives
         * in the pure, overflow-safe [QuickTenderCalculator] (UIX-8C-05). Kept so
         * existing callers/tests keep working; new code should call
         * [QuickTenderCalculator.options] directly.
         */
        fun quickTenders(due: Long): List<Long> = QuickTenderCalculator.options(due)
    }
}
