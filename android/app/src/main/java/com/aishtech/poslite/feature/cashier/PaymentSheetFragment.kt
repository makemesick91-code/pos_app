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
        val quicks = quickTenders(amountDue)
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

    private fun currentPaid(): Long? = RupiahMoney.parse(binding.inputSheetTender.text?.toString())

    private fun renderChange() {
        val paid = currentPaid()
        val sufficient = paid != null && RupiahMoney.isSufficient(paid, amountDue)
        when {
            paid == null -> binding.textSheetChange.text = getString(R.string.pay_sheet_enter_tender)
            !sufficient -> binding.textSheetChange.text = getString(
                R.string.pay_sheet_short,
                RupiahMoney.format(amountDue - paid),
            )
            else -> binding.textSheetChange.text = getString(
                R.string.pay_sheet_change_label,
                RupiahMoney.format(RupiahMoney.change(paid, amountDue)),
            )
        }
        binding.buttonSheetPayOnline.isEnabled = sufficient
        binding.buttonSheetPayOffline.isEnabled = sufficient
    }

    private fun confirm(offline: Boolean) {
        val paid = currentPaid() ?: return
        if (!RupiahMoney.isSufficient(paid, amountDue)) return
        (activity as? Host)?.onCashTender(paid, offline)
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
         * Up to three round-up cash shortcuts strictly greater than [due], on a
         * natural cash-denomination ladder. Pure and testable; never suggests a
         * value ≤ due (that is the separate "Uang Pas" exact button).
         */
        fun quickTenders(due: Long): List<Long> {
            if (due <= 0L) return emptyList()
            val steps = listOf(5_000L, 10_000L, 20_000L, 50_000L, 100_000L)
            return steps
                .map { step -> ((due + step - 1L) / step) * step }
                .filter { it > due }
                .distinct()
                .sorted()
                .take(3)
        }
    }
}
