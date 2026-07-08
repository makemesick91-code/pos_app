# Invoice Lifecycle Map (Sprint 23)

A SaaS billing invoice is a platform-to-tenant billing record — **not** a POS
cashier receipt. Issuing an invoice never triggers a payment gateway and never
auto-suspends a tenant.

## Statuses

`DRAFT`, `ISSUED`, `PARTIAL`, `PAID`, `OVERDUE`, `DISPUTED`, `VOIDED`, `ARCHIVED`.

## Allowed transitions

| From | To | Trigger |
|------|----|---------|
| DRAFT | DRAFT | edit metadata / add/update lines (totals recalculated) |
| DRAFT | ISSUED | `issue` (sets issue_date + due_date = issue + payment_terms_days) |
| DRAFT | VOIDED | `void` |
| ISSUED | PARTIAL | accepted payment evidence (paid < total) |
| ISSUED | PAID | accepted payment evidence (paid ≥ total) |
| ISSUED | OVERDUE | `mark-overdue` (manual) |
| ISSUED | DISPUTED | `mark-disputed` (manual) |
| ISSUED | VOIDED | `void` |
| PARTIAL | PAID | further accepted payment evidence |
| PARTIAL | OVERDUE | `mark-overdue` (manual) |
| PARTIAL | ISSUED | rejected/voided evidence rolls paid back to 0 |
| OVERDUE | PARTIAL / PAID | accepted payment evidence |
| OVERDUE | DISPUTED | `mark-disputed` (manual) |
| DISPUTED | PARTIAL / PAID | accepted payment evidence |
| any non-terminal | VOIDED | `void` (with reason) |

## Forbidden transitions

- No transition triggers a payment gateway, auto-charge, auto-suspension, or
  auto-renewal.
- A `PAID` invoice cannot be edited (archive/no-op only).
- A `VOIDED` invoice cannot receive payment evidence and cannot be re-opened.
- Totals (`subtotal/discount/tax/total`) are never client-supplied — they are
  recomputed server-side from invoice lines. `paid_amount`/`remaining_amount` are
  only mutated by payment-evidence review.

## Server-side total calculation

For each line: `line_total = (quantity × unit_amount) − discount + tax`.

Invoice: `subtotal = Σ(quantity × unit_amount)`, `discount = Σ line discount`,
`tax = Σ line tax`, `total = subtotal − discount + tax`,
`remaining = max(0, total − paid)`.
