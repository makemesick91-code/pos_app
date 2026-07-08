# Sprint 33 — Tenant Onboarding, Trial Activation & First-Branch Provisioning

## Scope

Turn the commercial SaaS foundation (Sprint 23–32) into a real, governed onboarding
flow for a brand-new UMKM/tenant from zero. A single, idempotent, transactional
request:

- creates the tenant,
- selects and resolves the plan (fail-closed on unknown),
- activates a governed, time-bounded trial,
- provisions the first branch (a `Store` — this codebase has no separate `Branch`),
- provisions the owner/admin user,
- provisions the first cashier user,
- prepares the first register/device setup (one-time hashed token only),
- seeds safe, deterministic, tenant-isolated default data,
- optionally prepares the trial-to-paid invoice (Sprint 30) and payment intent (Sprint 31),
- computes a deterministic onboarding checklist,
- audits every provisioning step with redacted metadata,
- enforces the Sprint 32 entitlement gate on every resource creation.

## Non-goals

- No Android onboarding UI (backend/governance only).
- No public/self-signup mutation (disabled by default; requires a signed approval token to ever enable).
- No React/Vue/heavy frontend dependency.
- No VPS deploy.
- No new plan/billing/entitlement semantics — Sprint 33 is additive and builds on the existing services.
- Does not touch the Sprint 23 `saas_billing_*` tables, nor the Sprint 12 `tenant_onboarding_runs` demo-data table.

## Commercial SaaS chain

```
Plan → Invoice → Payment Intent → Gateway Settlement → Collection → Entitlement Runtime Access
(S26)   (S30)       (S31)             (S31)              (S30)          (S32)
```

Onboarding consumes this chain end-to-end without bypassing any link:

- Plan resolution: Sprint 26 `TenantPlanResolver` / `TenantPlanRegistrar`.
- Invoice: Sprint 30 `TenantInvoiceService` (idempotent per tenant+period, amount from plan pricing).
- Payment intent: Sprint 31 `PaymentGatewayIntentService` (mock QRIS provider).
- Settlement + collection: Sprint 31 `PaymentGatewaySettlementService` → Sprint 30 `TenantPaymentCollectionService` (trusted collection state).
- Entitlement runtime access: Sprint 32 `EntitlementAccessService` / `EntitlementBillingStateService`.

Onboarding NEVER marks an invoice paid or unlocks paid entitlement directly; paid
access only ever follows the trusted collection state.

## Onboarding architecture

`App\Services\TenantOnboarding\TenantOnboardingService` is the single orchestrator
(`ONB-R001`). It calls only lower-level services, each of which owns one concern:

| Service | Responsibility |
| --- | --- |
| `TenantProvisioningService` | Create the tenant (idempotent, no duplicate identity). |
| `TrialActivationService` | Assign the resolved plan + create the time-bounded TRIAL subscription. |
| `FirstBranchProvisioningService` | Create the first store, enforcing `canCreateBranch`. |
| `OwnerAdminProvisioningService` | Create the owner (`tenant_owner`), enforcing `canCreateUser`. |
| `CashierProvisioningService` | Create the first cashier, enforcing `canCreateCashier`. |
| `DeviceRegisterProvisioningService` | Prepare register/device setup token, enforcing `canRegisterDevice`. |
| `TenantSeedDataService` | Seed safe default categories (deterministic, idempotent, tenant-isolated). |
| `TrialToPaidReadinessService` | Generate invoice (S30) + payment intent (S31); never marks paid. |
| `OnboardingChecklistService` | Compute the deterministic checklist. |
| `OnboardingAuditService` | Write every step to the provisioning trace with redacted metadata. |
| `OnboardingRedactor` | Redact secrets/PII from any metadata. |
| `OnboardingSummaryService` | Trial / decision summaries. |
| `OnboardingPlanReadinessService` | List trial-eligible plans. |
| `OnboardingGovernanceAuditService` | Audit governance wiring. |
| `OnboardingGoNoGoService` | Hard GO/WATCH/NO-GO gate. |

## Lifecycle state machine

```
draft -> provisioning -> trial_active -> (waiting_payment) -> completed
                      -> failed (auditable; retryable, idempotent)
                      -> cancelled (safe states only)
```

`waiting_payment` is entered when a trial-to-paid invoice is prepared. `paid_active`
is reserved for the trusted collection state (never set by onboarding directly).

## Idempotency model

- A run is keyed by a unique `idempotency_key` (`ONB-R005`). A replayed key resumes
  or returns the existing run.
- A completed run is returned untouched; a failed run is re-run.
- Each provisioning service reuses an existing subject (tenant/store/owner/cashier/
  subscription) rather than creating a duplicate (`ONB-R021`).
- Steps are keyed by `idempotency_key:step_key` (unique).

## Transaction / failure model

- All resource mutations run inside one DB transaction. Any failure rolls the whole
  transaction back, so there is never a half-created tenant (`ONB-R004`).
- The run row and a single failed-step row are written OUTSIDE the transaction so a
  failure always leaves an auditable failed state (`ONB-R020`).
- A denied entitlement decision is recorded to the `tenant_entitlement_decisions`
  trail and surfaced as a failed step with reason `DENIED_ENTITLEMENT` (`ONB-R023`).

## Data model

- `tenant_provisioning_runs` — one onboarding run: requested/resolved plan, status,
  idempotency key, owner/branch/cashier/register/device ids, trial window, invoice/
  intent ids, checklist JSON (redacted-safe), failure reason, timestamps.
- `tenant_provisioning_steps` — one row per step: step key, status, subject
  type/id, unique idempotency key, reason code, redacted metadata, failure reason.

Both tables are DELIBERATELY separate from the Sprint 12 `tenant_onboarding_runs`
demo-data table and the Sprint 23 `saas_billing_*` tables.

## Route matrix (platform.admin only, `/api/v1/admin/tenant-billing/onboarding`)

| Method | Path | Action |
| --- | --- | --- |
| GET | `/onboarding` | list runs |
| GET | `/onboarding/governance` | governance summary |
| POST | `/onboarding` | start/dry-run onboarding |
| GET | `/onboarding/{run}` | show run |
| GET | `/onboarding/{run}/checklist` | checklist |
| POST | `/onboarding/{run}/retry` | idempotent retry |
| POST | `/onboarding/{run}/cancel` | cancel (safe states) |
| POST | `/onboarding/{run}/invoice` | trial-to-paid invoice (S30) |
| POST | `/onboarding/{run}/payment-intent` | QRIS/mock intent (S31) |

There is NO public/self-signup mutation route and NO tenant route that mutates the
onboarding lifecycle.

## Command matrix

| Command | Purpose |
| --- | --- |
| `onboarding:plan-readiness` | List trial-eligible plans. |
| `onboarding:start` | Dry-run (default) or `--execute` with `--idempotency-key`. |
| `onboarding:checklist` | Deterministic checklist for a run. |
| `onboarding:trial-summary` | Active/expired trials + counts by status. |
| `onboarding:decision-summary` | Failed/blocked steps by reason code. |
| `onboarding:governance-audit` | Governance wiring audit (non-zero on FAIL). |
| `onboarding:go-no-go` | Hard Sprint 33 gate. |

## Audit / redaction model

Every mutation and every denied/failed step is written to `tenant_provisioning_steps`
with metadata redacted by `OnboardingRedactor` (drops password/token/secret/
signature/phone/email/name/address/NIK/card/body/payload keys). Owner/cashier
passwords are random write-only secrets — hashed and never stored, returned, or
logged (`ONB-R024`).

## Dependency graph

```
TenantOnboardingService
 ├─ TenantProvisioningService ─────────────→ Tenant
 ├─ TrialActivationService ────────────────→ TenantPlanResolver/Registrar, TenantPlanAssignment, TenantSubscription
 ├─ FirstBranchProvisioningService ────────→ EntitlementAccessService (canCreateBranch) → Store
 ├─ OwnerAdminProvisioningService ─────────→ EntitlementAccessService (canCreateUser) → User(tenant_owner)
 ├─ CashierProvisioningService ────────────→ EntitlementAccessService (canCreateCashier) → User(cashier)
 ├─ DeviceRegisterProvisioningService ─────→ EntitlementAccessService (canRegisterDevice) → hashed setup token
 ├─ TenantSeedDataService ─────────────────→ ProductCategory (idempotent defaults)
 ├─ TrialToPaidReadinessService ───────────→ TenantInvoiceService (S30), PaymentGatewayIntentService (S31)
 ├─ OnboardingChecklistService ────────────→ EntitlementAccessService (canRead)
 └─ OnboardingAuditService ────────────────→ tenant_provisioning_steps, EntitlementAuditService (S32)
```

## Rollback

Additive migrations only. To roll back: revert the branch / drop
`tenant_provisioning_steps` then `tenant_provisioning_runs`. No Sprint 23–32 table
or behavior is modified, so the prior commercial chain is unaffected.

## Deferred risks

- The device/register setup mints a hashed token but there is no Sprint 33 device
  activation endpoint; real activation remains the Sprint 10 Android device flow.
- Owner/cashier credentials are random write-only; credential delivery (invite/
  reset) is deferred to a later sprint.
- Self-signup remains disabled; the signed approval-token flow is scaffolded in
  config but not surfaced as a route.

## Rules

`ONB-R001..ONB-R026` are the canonical Sprint 33 rules, locked in
`backend/config/onboarding_governance.php`, mirrored in
`backend/config/pos_foundation.php` and `docs/PROJECT_RULES.md`, and exercised by
tests, the smoke script, and `onboarding:go-no-go`.
