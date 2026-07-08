# Sprint 33 — Tenant Onboarding, Trial Activation & First-Branch Provisioning — Evidence

Baseline: Sprint 32 merged (`f3548643df80eff0871b095a5d2fc29add886ebc`), main CI green,
GO tag `sprint-32-plan-entitlement-runtime-enforcement-subscription-access-control-go`.

## Scope delivered

A single, idempotent, transactional onboarding flow that creates a tenant, resolves
its plan (fail-closed), activates a governed time-bounded trial, provisions the first
branch/owner/cashier, prepares the register/device setup, seeds safe defaults, and
prepares (but never settles) the trial-to-paid invoice + payment intent — every step
enforcing the Sprint 32 entitlement gate and audited with redacted metadata.

## Commercial chain confirmed

`Plan (S26) → Invoice (S30) → Payment Intent (S31) → Gateway Settlement (S31) →
Collection (S30) → Entitlement Runtime Access (S32)`. Onboarding consumes each link
through its owning service and never marks an invoice paid or unlocks paid
entitlement directly.

## Tables / models

- `tenant_provisioning_runs` — `App\Models\TenantProvisioningRun`
- `tenant_provisioning_steps` — `App\Models\TenantProvisioningStep`

Separate from the Sprint 12 `tenant_onboarding_runs` demo-data table and the Sprint 23
`saas_billing_*` tables.

## Services (App\Services\TenantOnboarding)

`TenantOnboardingService` (orchestrator), `TenantProvisioningService`,
`TrialActivationService`, `FirstBranchProvisioningService`,
`OwnerAdminProvisioningService`, `CashierProvisioningService`,
`DeviceRegisterProvisioningService`, `TenantSeedDataService`,
`TrialToPaidReadinessService`, `OnboardingChecklistService`, `OnboardingAuditService`,
`OnboardingRedactor`, `OnboardingSummaryService`, `OnboardingPlanReadinessService`,
`OnboardingGovernanceAuditService`, `OnboardingGoNoGoService`, plus the
`OnboardingRequestData` DTO and `OnboardingException`.

## HTTP surface

`platform.admin`-only routes under `/api/v1/admin/tenant-billing/onboarding`
(list, show, governance, store, retry, cancel, checklist, invoice, payment-intent).
Request: `StartTenantOnboardingRequest`. Resource: `TenantProvisioningRunResource`.
No public/self-signup mutation route; no tenant onboarding-lifecycle mutation route.

## Commands

`onboarding:plan-readiness`, `onboarding:start` (dry-run default; `--execute`
+`--idempotency-key`), `onboarding:checklist`, `onboarding:trial-summary`,
`onboarding:decision-summary`, `onboarding:governance-audit`, `onboarding:go-no-go`.

## Rules ONB-R001..ONB-R026

Locked in `backend/config/onboarding_governance.php`, mirrored in
`backend/config/pos_foundation.php` and `docs/PROJECT_RULES.md`:

- `ONB-R001` central `TenantOnboardingService` orchestrator.
- `ONB-R002` plan resolves through `TenantPlanResolver`.
- `ONB-R003` unknown plan fails closed (no unlimited fallback).
- `ONB-R004` transactional + idempotent.
- `ONB-R005` unique idempotency key required for mutation.
- `ONB-R006` tenant creation audit-logged.
- `ONB-R007` trial time-bounded + audit-logged.
- `ONB-R008` first branch required unless disabled.
- `ONB-R009` owner/admin required.
- `ONB-R010` cashier respects Sprint 32 limit.
- `ONB-R011` device/register respects Sprint 32 limit.
- `ONB-R012` safe, deterministic, tenant-isolated seed data.
- `ONB-R013` no step bypasses `EntitlementAccessService`.
- `ONB-R014` trial-to-paid uses Sprint 30 invoice/collection.
- `ONB-R015` payment intent uses Sprint 31 services.
- `ONB-R016` failed/cancelled/expired payment never activates paid access.
- `ONB-R017` manual suspension always wins.
- `ONB-R018` public self-signup mutation disabled by default.
- `ONB-R019` no tenant/public onboarding-lifecycle mutation.
- `ONB-R020` failure leaves an auditable failed state.
- `ONB-R021` retry idempotent, no duplicates.
- `ONB-R022` deterministic, explainable checklist.
- `ONB-R023` denied/blocked step audit-logged, redacted.
- `ONB-R024` no secrets/PII in output.
- `ONB-R025` platform-admin bypass explicit + audited.
- `ONB-R026` go/no-go verifies the full commercial chain.

## Key confirmations

- Idempotent execute: re-running the same key yields 1 run / 1 tenant / 1 store /
  2 users / 1 subscription / 1 plan assignment (no duplication).
- Fail-closed: an unknown plan raises `UNKNOWN_PLAN` before any mutation.
- Entitlement enforced: a fresh tenant in a blocked billing state is denied
  `DENIED_ENTITLEMENT` on first-branch provisioning and the decision is audited.
- No PII/secrets in step metadata (verified: only ids, roles, booleans, counts, and a
  non-reversible setup fingerprint).
- Manual suspension still wins; a paid invoice never lifts it (Sprint 25/32 semantics
  untouched).

## Regression (Sprint 24–32)

Prior gate commands remain registered and are asserted by `onboarding:go-no-go`
(`subscription-renewal:go-no-go`, `tenant-lifecycle:go-no-go`, `tenant-plan:go-no-go`,
`report-export-metering:go-no-go`, `usage-ledger:go-no-go`, `export-governance:go-no-go`,
`billing:go-no-go`, `payment-gateway:go-no-go`, `entitlement:go-no-go`).

## Tests / CI / smoke

- Backend tests: `backend/tests/Feature/Sprint33*` and `backend/tests/Unit/Sprint33*`.
- Smoke: `scripts/sprint33_smoke.sh` (structural + command + governance-gate + runtime).
- CI: `.github/workflows/sprint33-ci.yml` (backend tests, Android build+unit, prior
  gates, governance-audit, go-no-go, smoke, ONB-R grep, sqlite env).

_Evidence counts (tests/assertions, smoke PASS/FAIL) are filled in the PR body after
the full gate run._

## Deferred risks

- No Sprint 33 device-activation endpoint (real activation stays the Sprint 10 flow).
- Credential delivery (invite/reset) deferred.
- Self-signup remains disabled; signed approval-token flow scaffolded, not routed.

## Rollback

Revert the branch or drop `tenant_provisioning_steps` then
`tenant_provisioning_runs`. No Sprint 23–32 table/behavior is modified.
