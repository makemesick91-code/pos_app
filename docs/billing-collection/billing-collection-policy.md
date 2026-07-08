# Billing Collection Policy (Sprint 23)

Aish POS Lite / POS Android SaaS — **Billing Collection Governance Foundation**.

SaaS billing collection is **platform-to-tenant** billing governance. It is **not**
the POS cashier/customer payment domain (QRIS / cash at a tenant store). The two
domains must never be mixed.

## Scope

Sprint 23 establishes manual, admin-governed, evidence-backed billing collection:

- **SaaS billing account** — a platform-to-tenant billing profile. May reference an
  existing tenant; creating an account never creates a tenant, and a status change
  never suspends tenant access.
- **Billing cycle** — a governance period (`DRAFT → OPEN → LOCKED → CLOSED →
  ARCHIVED`) that groups invoices.
- **Billing invoice** — a platform-to-tenant invoice (`DRAFT → ISSUED → PARTIAL /
  PAID / OVERDUE / DISPUTED / VOIDED / ARCHIVED`). Totals are calculated
  server-side from invoice lines.
- **Manual payment evidence** — a manually recorded proof of payment that, once
  reviewed and accepted, updates the invoice paid/remaining state.
- **Collection activity** — manual notes/calls/follow-ups. Channel-named types
  (WhatsApp/email) are notes only.
- **Risk & sign-off governance** — CRITICAL/HIGH/MEDIUM risk gating and role-based
  sign-off leading to a Billing Collection GO / WATCH / NO-GO decision.

## Hard guardrails (Sprint 23)

Billing collection MUST NOT:

- integrate a real payment gateway for SaaS billing;
- auto-charge or auto-debit tenants;
- automate subscription payment collection;
- generate public payment links or a public self-service checkout;
- auto-suspend tenant access based on unpaid billing;
- auto-renew subscriptions or change device limits from payment evidence;
- send real invoice email, WhatsApp, Slack, or Telegram messages;
- integrate a real CRM or accounting/e-faktur system;
- call the QRIS runtime (a `MANUAL_QRIS_REFERENCE` is a label only).

All of these are locked to `false` in `backend/config/billing_collection.php`; a
`true` value forces the readiness decision to NO-GO.

## Governance model

- All billing collection APIs live under `/api/v1/admin` behind `auth:sanctum` +
  `platform.admin`. Tenant users cannot access them.
- Every mutation is audit-logged via `AdminAuditLogger`.
- Invoice totals are server-calculated from lines; `paid_amount`/`remaining_amount`
  are only ever mutated by the payment-evidence review service.
- Free-text and metadata are secret-redacted before persistence.

See also [manual-payment-evidence-policy.md](manual-payment-evidence-policy.md),
[invoice-lifecycle-map.md](invoice-lifecycle-map.md),
[manual-collection-playbook.md](manual-collection-playbook.md),
[overdue-dispute-governance.md](overdue-dispute-governance.md),
[billing-risk-register.md](billing-risk-register.md), and
[billing-collection-go-watch-no-go-report.md](billing-collection-go-watch-no-go-report.md).
