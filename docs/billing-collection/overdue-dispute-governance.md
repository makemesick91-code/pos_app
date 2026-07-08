# Overdue & Dispute Governance (Sprint 23)

Overdue and dispute handling is **manual-review-first**. No unpaid or disputed
invoice ever triggers an automatic consequence for the tenant.

## Overdue

- An issued/partial invoice past its `due_date` may be **manually** marked
  `OVERDUE` (`POST /billing/invoices/{invoice}/mark-overdue`).
- Marking overdue is a governance flag only. It does **not** auto-suspend the
  tenant, auto-charge, or change the subscription/device limits.
- An overdue invoice can still receive manual payment evidence and move to
  `PARTIAL` / `PAID`.
- Persistent overdue should be recorded as a billing collection risk in the
  `PAYMENT_DELAY` or `COLLECTION_SLA` area.

## Dispute

- An invoice under dispute is **manually** marked `DISPUTED`
  (`POST /billing/invoices/{invoice}/mark-disputed`) with a note.
- A disputed invoice pauses collection follow-up while a human resolves it.
- Resolution is manual: accept payment evidence (→ `PARTIAL`/`PAID`), void the
  invoice (with a reason), or re-issue a corrected invoice.
- A dispute about invoice accuracy should be recorded as a risk in the
  `INVOICE_ACCURACY` or `DISPUTE` area.

## No automatic action

- No auto-suspension of tenant access.
- No auto-charge / auto-debit.
- No auto subscription renewal / cancellation.
- No automatic device-limit change.
- No real message sending.

## Risk escalation

Overdue/dispute situations that threaten the collection SLA or legal/privacy posture
must be escalated through the [billing risk register](billing-risk-register.md).
Open CRITICAL/HIGH risks force a NO-GO until mitigated or a valid accepted risk is
documented.
