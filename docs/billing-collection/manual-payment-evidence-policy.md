# Manual Payment Evidence Policy (Sprint 23)

Manual payment evidence is the **only** way a SaaS billing invoice is marked paid
in Sprint 23. There is no payment gateway, no auto-charge, and no webhook.

## Lifecycle

```
SUBMITTED → UNDER_REVIEW → ACCEPTED
                        ↘ REJECTED
(any of the above)      → VOIDED
```

- **SUBMITTED** — an admin records a manual proof of payment (amount, method, label,
  reference, notes). Only an invoice in `ISSUED / PARTIAL / OVERDUE / DISPUTED` may
  receive evidence. A `VOIDED` (or `PAID` / `DRAFT`) invoice never receives evidence.
- **UNDER_REVIEW** — an admin is reviewing the evidence.
- **ACCEPTED** — the amount is applied to the invoice through the review service.
  The invoice's `paid_amount` is recomputed from **all ACCEPTED evidences**, capped
  at the invoice total (overpayment is capped safely). The invoice moves to
  `PARTIAL` or `PAID` accordingly.
- **REJECTED** — the evidence is rejected with a required reason. A rejected
  evidence **never** updates `paid_amount`. Rejecting a previously accepted
  evidence rolls back its applied amount.
- **VOIDED** — the evidence is discarded. Voiding a previously accepted evidence
  rolls back its applied amount.

## Payment methods

`BANK_TRANSFER`, `CASH_DEPOSIT`, `MANUAL_QRIS_REFERENCE`, `OTHER_MANUAL`.

`MANUAL_QRIS_REFERENCE` is a **label only** — it records that a manual QRIS
reference was provided out-of-band. It never calls the QRIS runtime or any payment
gateway API.

## Prohibitions

- No payment gateway payloads are stored.
- No secrets (card numbers, CVV, gateway keys, bank credentials) are stored;
  free-text and metadata are secret-redacted.
- `paid_amount` / `remaining_amount` can never be forced directly by a client — they
  are only ever mutated by the payment-evidence review service.
