# Payment / QRIS Monitoring Checklist

> Sprint 16 — Pilot Monitoring & Hypercare Foundation.
> Payment gateway credentials are backend-only; never in the device app or docs.

Monitors cash and QRIS payment flows during the pilot. Maps to the
`cashier_cash_sale` and `qris_payment_status` signals.

## Checks

- [ ] **Cash sale success** — cash sale posts and is accepted; receipt eligible.
- [ ] **QRIS pending status** — QRIS payment starts in `PENDING`; QR issued by
      backend.
- [ ] **QRIS paid status / webhook** — status transitions `PENDING → PAID` via
      backend webhook simulation (no real gateway secret in evidence).
- [ ] **Payment status endpoint** — `/api/v1/payments/{payment}/status` returns
      the authoritative backend status.
- [ ] **No double payment** — a sale cannot be paid twice; idempotent payment.
- [ ] **Receipt eligibility** — receipt is only issued for a settled/accepted
      payment.

## Evidence required

- Payment status transitions (anonymized, no gateway secret).
- Webhook simulation result reference.

## Escalation

- QRIS stuck `PENDING` with confirmed paid funds → CRITICAL.
- Double payment accepted → BLOCKER.
- Receipt issued for unpaid QRIS → CRITICAL.
