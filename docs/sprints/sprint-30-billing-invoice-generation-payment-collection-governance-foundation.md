# Sprint 30 — Billing Invoice Generation & Payment Collection Governance Foundation

**Status:** implemented on `feature/sprint-30-billing-invoice-generation-payment-collection-governance-foundation`.
**Base branch:** `main` (Sprint 29 GO `e509463`).
**Gate:** `billing:go-no-go`.

## Scope

Adds a server-side billing foundation: canonical billing periods, plan-priced
idempotent invoice generation, a controlled invoice status / payment collection
lifecycle, a manual payment collection state, platform-admin visibility + mutation
with audit + redaction, CLI governance commands, and the `BIL-R001..R016` rules.
It is a foundation, **not** a payment-gateway integration and **not** a fake
payment-success flow.

## Runtime changes

- **Config** — `backend/config/billing_governance.php`: default currency, monthly
  period policy (`due_days`, timezone), per-plan pricing (source of truth),
  status/collection/payment vocabularies, method/source allowlists, partial /
  overpayment policy, eight guardrail flags (all false), `BIL-R001..R016`,
  required docs/commands, prior-sprint gate contract.
- **Migrations** — `tenant_billing_invoices` (unique `tenant+period+source`,
  unique `idempotency_key`), `tenant_billing_payments` (unique `idempotency_key`).
- **Models** — `TenantBillingInvoice` (collected/outstanding helpers),
  `TenantBillingPayment`.
- **Services** (`App\Services\Billing`) — `BillingPeriod`, `BillingPeriodService`,
  `TenantInvoicePricingService`, `TenantInvoiceNumberGenerator`,
  `TenantInvoiceStatusService`, `TenantInvoiceService`,
  `TenantPaymentCollectionService`, `BillingSummaryService`,
  `BillingMetadataSanitizer`, `BillingAuditService`,
  `BillingGovernanceAuditService`, `BillingGoNoGoService`,
  `BillingGovernanceException`.
- **Admin** — `AdminBillingInvoiceController`, `AdminBillingPaymentController`,
  `AdminBillingGovernanceController`; requests `GenerateTenantInvoiceRequest`,
  `RecordInvoicePaymentRequest`, `PaymentReasonRequest`; resources
  `TenantBillingInvoiceResource`, `TenantBillingPaymentResource`; 8 routes under
  `/api/v1/admin` behind `platform.admin` (4 mutations audit-logged).
- **Commands** — `billing:period-summary`, `billing:invoice-generate` (dry-run
  default), `billing:invoice-summary`, `billing:collection-summary`,
  `billing:governance-audit`, `billing:go-no-go`.
- **Rules** — `backend/config/pos_foundation.php` guardrail flags + `sprint_30`;
  `docs/PROJECT_RULES.md` Sprint 30 runtime rule (`BIL-R001..R016`).

## Backward compatibility

- No existing table, model, service, controller, route, policy, Gate, Spatie
  role/permission, schema, or config semantic was changed. Only additive.
- Sprint 23 `saas_billing_*` (collection governance) is untouched; the new
  `tenant_billing_*` layer is a separate, plan-priced generation layer keyed to
  the Sprint 26 `tenant_plans`.
- Sprint 24 renewal/dunning, Sprint 25 lifecycle, Sprint 26 plan, Sprint 27–29
  ledger/export code are unchanged.

## Behavior summary

- **Billing period:** deterministic monthly `YYYY-MM`, `due_at = start + 7d`.
- **Invoice generation:** plan-priced, idempotent per tenant + period, refuses
  no-price (no silent zero), issued on create, collection state from due date.
- **Payment collection:** manual record (idempotent, no overpayment/partial by
  default), mark-failed/cancel; failed/cancelled never marks paid; paid never
  lifts manual suspension.
- **Renewal/dunning integration:** read-only summaries; never bypasses services,
  never marks paid, never duplicate-generates.

## Tests

`BillingPeriodTest`, `BillingInvoiceTest`, `BillingPaymentTest`,
`PaymentCollectionTest`, `BillingRenewalIntegrationTest`, `BillingGovernanceTest`
(rules lock + go-no-go + commands + admin authorization + lifecycle/plan
regression). Prior-sprint suites (`SubscriptionRenewal`, `TenantLifecycle`,
`TenantPlan`, `ExportGovernance`, `UsageLedger`) remain green.

## Governance / gate evidence

`billing:go-no-go` GO; `billing:governance-audit` GO;
`export-governance:go-no-go`, `usage-ledger:go-no-go --strict`,
`report-export-metering:go-no-go`, `tenant-plan:go-no-go`,
`tenant-lifecycle:go-no-go` all remain green. Smoke: `scripts/sprint30_smoke.sh`.
CI: `.github/workflows/sprint30-ci.yml`.

## Risks / deferred

- Governed invoice adjustment / credit-note flow for issued invoices is deferred
  (BIL-R013 forbids silent mutation until then).
- Partial payment and overpayment are config-gated off by default.
- Real payment-gateway settlement is intentionally out of scope.

## Rollback

Revert the feature branch merge commit and drop the two `tenant_billing_*`
migrations; no prior-sprint data or behavior depends on this layer.
