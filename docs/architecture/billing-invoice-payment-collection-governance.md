# Billing Invoice Generation & Payment Collection Governance (Sprint 30)

This document describes the server-side billing foundation added in Sprint 30. It
is a **governance foundation**, not a payment-gateway integration: invoices are
generated from plan pricing and payments are recorded facts. All authority is
server-side; the Android/POS client is never a billing authority.

## Dependency graph

```
tenant
  └─ active plan assignment (Sprint 26)  ──► TenantPlanResolver ─► plan_key
                                                       │
config/billing_governance.pricing[plan_key] ──────────┘
                                                       ▼
TenantInvoicePricingService  ──► amount / currency (refuses no-price, no silent zero)
                                                       │
BillingPeriodService ─► BillingPeriod (period_key, start, end, due_at)   │
        │                                              │                 │
        └──────────────► TenantInvoiceService.generate() ◄───────────────┘
                                   │  (idempotent per tenant+period, BIL-R002/R005)
                                   ▼
                         tenant_billing_invoices
                          status: draft→issued→(void|cancelled)     (TenantInvoiceStatusService)
                          collection_state: not_due|pending|paid|failed|overdue|written_off|cancelled
                                   │
                                   ▼
              TenantPaymentCollectionService.record/markFailed/cancel
                                   │  (idempotent, no overpayment/partial, BIL-R009/R010)
                                   ▼
                         tenant_billing_payments ──► refreshCollectionState()
                                   │
                                   ▼
                         BillingAuditService ─► AdminAuditLogger ─► admin_audit_logs (redacted)

read-only reads:
  BillingSummaryService ─► billing:invoice-summary / billing:collection-summary / admin collection-summary
  BillingGovernanceAuditService ─► billing:governance-audit / admin governance-summary
  BillingGoNoGoService ─► billing:go-no-go (aggregates audit + Sprint 24–29 gates)

integration boundaries (read-only, never bypass services):
  subscription renewal / dunning (Sprint 24) ─► may READ invoice/collection summary
  tenant lifecycle / manual suspension (Sprint 25) ─► paid invoice never lifts suspension (BIL-R011)
  tenant plan / entitlement / usage (Sprint 26) ─► plan price = pricing source of truth (BIL-R003)
  usage ledger / export governance (Sprint 27–29) ─► untouched, gates stay green (BIL-R014)
```

## Billing period (BIL-R001)

`BillingPeriodService` is the only place a period is computed. Monthly only for
the foundation. `period_key` is `YYYY-MM` in the configured billing timezone
(`Asia/Jakarta`); `period_start`/`period_end` are civil-day aligned; `due_at` is
`period_start + due_days` (default 7). Given the same date + config the result is
byte-for-byte stable — no reliance on a random clock, safe for deterministic tests.

## Invoice generation (BIL-R002, BIL-R003, BIL-R005)

`TenantInvoiceService::generate(tenant, periodKey, source)`:

1. Resolves the period (`BillingPeriodService`) and the price
   (`TenantInvoicePricingService` → active plan → `config('billing_governance.pricing')`).
2. Inside a transaction with `lockForUpdate`, returns any existing **live**
   invoice for the tenant + period unchanged (idempotent), else creates one.
3. New invoice: `subtotal = total = plan amount`, `discount = tax = 0`, number
   `INV-{YYYYMM}-{tenantId}`, `idempotency_key = sha256(invoice:tenant:period:source)`.
4. Issues the invoice and sets the collection axis from the due date.
5. Audit-logs `billing.invoice.generated` (redacted).

A tenant with no configured/active pricing is **refused** (`BILLING_NO_PLAN_PRICING`
/ `BILLING_PLAN_PRICING_INACTIVE` / `BILLING_ZERO_PRICE_NOT_FREE`) — never a silent
zero invoice. A plan price change later never mutates a stored `total_amount` (BIL-R013).

## Invoice status & collection lifecycle (BIL-R004)

`TenantInvoiceStatusService` owns every transition:

- `draft → issued`, `issued → void`, `issued → cancelled`; `void`/`cancelled` terminal.
- A settled invoice (any counting payment) cannot be void/cancelled — refused
  pending a governed reversal (out of scope for the foundation).
- `refreshCollectionState()` recomputes `collection_state` from counting payments:
  `paid` when collected ≥ total, else `overdue` past `due_at`, else `pending`.

## Payment collection (BIL-R009, BIL-R010)

`TenantPaymentCollectionService`:

- `record()` — positive amount only; rejects overpayment and (unless configured)
  partial payment; idempotent by key; sets invoice `paid` only when the collected
  total covers the invoice total.
- `markFailed()` / `cancel()` — only from `pending`/`recorded`; the payment stops
  counting and the invoice collection state is recomputed, so it can never remain
  falsely paid.
- Every mutation is audit-logged; metadata is redacted.

## Integration boundaries

- **Renewal/dunning (Sprint 24)** may read invoice/collection summaries but must
  mutate only through these services (BIL-R012); it never marks an invoice paid
  and never duplicate-generates (idempotency guarantees this).
- **Lifecycle (Sprint 25)** — recording a payment / paying an invoice never lifts
  a manual suspension (BIL-R011). Billing admin routes stay platform-admin
  accessible; tenant operational routes stay blocked by `tenant.lifecycle`.
- **Plan (Sprint 26)** — pricing is the source of truth; entitlements/usage limits
  are unaffected by invoice generation.
- **Usage ledger / export governance (Sprint 27–29)** — untouched; all gates green.

## Admin surface (BIL-R007)

All under `/api/v1/admin/...` behind `platform.admin`:

| Method | URI | Purpose |
| --- | --- | --- |
| GET | `/billing/invoices` | list invoices (filterable) |
| GET | `/tenants/{tenant}/billing/invoices` | tenant invoices |
| POST | `/tenants/{tenant}/billing/invoices/generate` | idempotent generation |
| POST | `/billing/invoices/{invoice}/payments` | record manual payment |
| POST | `/billing/payments/{payment}/mark-failed` | mark payment failed (reason req.) |
| POST | `/billing/payments/{payment}/cancel` | cancel payment (reason req.) |
| GET | `/billing/collection-summary` | redacted collection summary |
| GET | `/billing/governance-summary` | governance audit signals |

There is deliberately **no** tenant/public route that mutates invoice/payment state.

## Commands & gate

`billing:period-summary`, `billing:invoice-generate` (dry-run default, `--apply`),
`billing:invoice-summary`, `billing:collection-summary`, `billing:governance-audit`,
`billing:go-no-go`. `billing:go-no-go` is the Sprint 30 release gate (BIL-R015) and
aggregates the billing governance audit plus the Sprint 24–29 prior-sprint gates.
