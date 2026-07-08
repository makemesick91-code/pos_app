# Sprint 23 — Billing Collection Governance Foundation

## Objective

Establish the **SaaS Billing Collection Governance** foundation after Sprint 20
commercial packaging, Sprint 21 public website, and Sprint 22 sales pipeline. All
collection is **manual, admin-governed, evidence-backed, manual-review-first, and
secret-safe**. There is no payment gateway automation, no auto-charge, and no auto
tenant suspension.

SaaS billing collection is **platform-to-tenant** billing governance and is kept
strictly separate from the existing POS QRIS/cash **customer** payment domain.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`
- `docs/PROJECT_RULES.md`
- `docs/sprints/sprint-0-*.md` … `docs/sprints/sprint-22-*.md`
- `docs/commercial/*`, `docs/public-website/*`, `docs/sales-pipeline/*`

## Previous Sprint Foundation Lock

- Sprint 22 GO tag: `sprint-22-lead-management-sales-pipeline-readiness-foundation-go`
- Previous main HEAD: `b9ea037`

## Graphify Summary

- **Reused:** `Tenant`, `TenantSubscription`, `SubscriptionPlan`, `RegisteredDevice`,
  `platform.admin` guard, `AdminAuditLog` + `AdminAuditLogger`, the Sprint 13–22 gate
  command contract, `scripts/android_release_readiness.sh`, existing CI patterns.
- **New namespace:** `App\Services\BillingCollection` (8 services + 1 trait).
- **New domain tables:** 8 (`saas_billing_*`).
- **Risk:** confusing SaaS billing with POS customer payments — mitigated by keeping
  a separate domain, tables, services, and docs, and by explicit guardrails.

## Scope

Runtime implementation (not docs-only): migrations, models, services, admin
controllers, requests, resources, commands, config, docs, tests, smoke, CI,
PROJECT_RULES + README + pos_foundation updates.

**Not** implemented (forbidden in Sprint 23): real payment gateway, auto-charge,
subscription payment automation, QRIS billing automation, Midtrans/Xendit/Duitku
integration, real invoice email / WhatsApp / Slack sending, real CRM / accounting /
e-faktur integration, auto tenant suspension / activation / renewal, automatic
device-limit change, public payment portal / link / self-service checkout, Play
Store deploy, APK/AAB/keystore commit, Android billing/admin UI.

## Database Implementation

| Table | Purpose |
|-------|---------|
| `saas_billing_accounts` | platform-to-tenant billing profile |
| `saas_billing_cycles` | governance billing periods |
| `saas_billing_invoices` | platform-to-tenant invoices (server-calculated totals) |
| `saas_billing_invoice_lines` | invoice lines (server-calculated line_total) |
| `saas_billing_payment_evidences` | manual payment evidence |
| `saas_billing_collection_activities` | manual collection activities |
| `saas_billing_collection_risks` | risk register (severity/accepted-risk gating) |
| `saas_billing_collection_signoffs` | append-only role-based sign-offs |

## Models

`SaasBillingAccount`, `SaasBillingCycle`, `SaasBillingInvoice`,
`SaasBillingInvoiceLine`, `SaasBillingPaymentEvidence`,
`SaasBillingCollectionActivity`, `SaasBillingCollectionRisk`,
`SaasBillingCollectionSignoff`.

## Services (`App\Services\BillingCollection`)

`BillingAccountService`, `BillingCycleService`, `BillingInvoiceService`,
`BillingPaymentEvidenceService`, `BillingCollectionActivityService`,
`BillingCollectionRiskGovernanceService`, `BillingCollectionReadinessService`,
`BillingCollectionGoNoGoService`, and the `SanitizesBillingCollectionText` trait.

## Admin APIs

All under `/api/v1/admin/billing/*`, protected by `auth:sanctum` + `platform.admin`:
accounts, cycles (open/lock/close), invoices (lines/issue/mark-overdue/mark-disputed/
void), payment-evidences (under-review/accept/reject/void), activities
(complete/cancel), risks (accept-risk/close), signoffs, plus read-only readiness,
invoice-summary, collection-summary, and go-no-go.

## Commands

`billing-collection:readiness`, `billing-collection:invoice-summary`,
`billing-collection:collection-summary`, `billing-collection:go-no-go` — each with
`--json` and `--strict`.

## Docs

`docs/billing-collection/`: billing-collection-policy, manual-payment-evidence-policy,
invoice-lifecycle-map, manual-collection-playbook, overdue-dispute-governance,
billing-risk-register, billing-collection-go-watch-no-go-report.

## PROJECT_RULES Update

Foundation Lock Index extended through Sprint 23; added the "Sprint 23 Billing
Collection Governance Foundation Runtime Rule" section (43 mandatory rules).

## README Update

Added the "Sprint 23 — Billing Collection Governance Foundation" section.

## CI Update

`.github/workflows/sprint23-ci.yml`: foundation-and-smoke, backend-tests (PHP 8.5),
prior-sprint-gates-13-22, billing-collection-gate, android-build-test (JDK 21).

## Tests

`BillingAccountServiceTest`, `BillingCycleServiceTest`, `BillingInvoiceServiceTest`,
`BillingPaymentEvidenceServiceTest`, `BillingCollectionActivityServiceTest`,
`BillingCollectionRiskGovernanceServiceTest`, `BillingCollectionReadinessServiceTest`,
`BillingCollectionGoNoGoServiceTest`, `BillingCollectionAdminApiTest`,
`BillingCollectionCommandsTest`, `BillingCollectionSecurityScanTest`,
`BillingCollectionRegressionRouteTest`.

## Android Compatibility

No Android business flow or UI changed. Package `com.aishtech.poslite`, minSdk 26,
targetSdk 35 intact. `assembleDebug` + `testDebugUnitTest` remain the CI build gate.

## Guardrails

Every automation flag in `config/billing_collection.php` is `false`; a `true` value
forces NO-GO. No payment gateway, no auto-charge, no auto tenant suspension, no auto
renewal, no real message/CRM/accounting integration, no public payment link.

## Validation Commands

```bash
bash scripts/sprint23_smoke.sh
bash scripts/android_release_readiness.sh
cd backend
php artisan migrate --force
php artisan billing-collection:readiness --json
php artisan billing-collection:invoice-summary --json
php artisan billing-collection:collection-summary --json
php artisan billing-collection:go-no-go --json
php artisan test
```

## Validation Results

Recorded on the PR / CI run. On a fresh database the readiness/go-no-go decision is
NO_GO until docs exist and all required sign-off roles approve; with docs present and
no data, the decision is WATCH (missing sign-off roles). Invoice/collection summaries
are GO and secret-safe.

## GO Criteria

Backend tests pass; Android `assembleDebug` + `testDebugUnitTest` green; Sprint 13–22
regression gates pass; billing collection gate runs cleanly; docs present; no blocking
risks; required sign-offs valid.

## No-Go Checks

Missing docs/config; open CRITICAL/HIGH risk without a valid accepted risk; rejected
sign-off; any real gateway/automation/message-sending introduced; tenant
auto-suspension introduced.

## Follow-up for Sprint 24

Consider automated reminders (still governance-gated), accounting export governance,
and dunning workflow modelling — all remaining manual/no-op until explicitly
unlocked.
