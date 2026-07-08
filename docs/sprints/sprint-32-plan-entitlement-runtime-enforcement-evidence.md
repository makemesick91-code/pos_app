# Sprint 32 — Plan Entitlement Runtime Enforcement & Subscription Access Control — Evidence

Baseline: Sprint 31 merged (`61708e2`), GO tag `sprint-31-payment-gateway-qris-settlement-governance-foundation-go`.
Architecture: `docs/architecture/sprint-32-plan-entitlement-runtime-enforcement-subscription-access-control.md`.
Rules: `ENT-R001..R024` in `docs/PROJECT_RULES.md`, `backend/config/entitlement_governance.php`, `backend/config/pos_foundation.php`.

## What shipped

- **Config**: `backend/config/entitlement_governance.php` — runtime enforcement on by default, fail-closed on unknown plan, safe trial/grace/suspended posture, limit/feature/export/report keys, reason codes, hard guardrails (all false), `ENT-R001..R024`, command/prior-gate/doc contracts.
- **Data**: `tenant_entitlement_decisions` table + `TenantEntitlementDecision` model (denied/degraded/read_only/bypassed audit trail; redacted metadata; no tenant writer).
- **Services** (`App\Services\Entitlements`): `EntitlementAccessService` (central gate), `EntitlementBillingStateService`, `EntitlementUsageService`, `EntitlementAuditService`, `EntitlementRedactor`, `EntitlementSummaryService`, `EntitlementGovernanceAuditService`, `EntitlementGoNoGoService`, `EntitlementDecision` (DTO).
- **Middleware**: `EnsureTenantCanWrite` (`entitlement.write`), `EnsureFeatureEntitled` (`entitlement.feature`), `EnsureExportEntitled` (`entitlement.export`), `EnsureReportEntitled` (`entitlement.report`) — registered in `bootstrap/app.php`.
- **Route wiring**: `entitlement.write` on the operational business group; `tenant.usage.limit:devices.max` on device registration; `entitlement.report:reports.daily-sales` on daily-sales; `entitlement.export:reports.daily-sales.csv` after the Sprint 27 export meter.
- **Admin surface** (`platform.admin`, read-only): `AdminTenantEntitlementAccessController`, `AdminEntitlementDecisionController` under `tenant-billing/entitlements/*`.
- **Commands**: `entitlement:plan-summary|usage-summary|access-check|decision-summary|governance-audit|go-no-go`.
- **Tests / smoke / CI / docs** as below.

## Enforcement points confirmed

| Point | Mechanism | Evidence |
|---|---|---|
| branch limit | canCreateBranch → OVER_QUOTA | test + smoke probe `BRANCH_AT=denied` |
| user/cashier limit | canCreateUser/canCreateCashier → users.max | test |
| device limit | canRegisterDevice + `tenant.usage.limit:devices.max` | test + route |
| outlet/register limit | canCreateOutletOrRegister → branches.max | test |
| feature entitlement | canUseFeature / entitlement.feature | test (FEATURE_NOT_IN_PLAN) |
| export/report entitlement | canUseExport/Report / entitlement.export/report | test |
| grace unpaid | unpaid_within_grace degraded-allowed | test |
| suspended / read-only | entitlement.write + billing state | test + probe `SUSPENDED_WRITE=MANUALLY_SUSPENDED` |
| trial vs paid | active_trial / trial_expired read-only | test |
| over-quota | deny create, keep read | test + probe `READ_OVERQUOTA=allowed` |
| denied audit | tenant_entitlement_decisions row | test (assertDatabaseHas) |

## Key confirmations

- Manual suspension wins over a paid invoice; paid invoice never lifts suspension (`test_manual_suspension_wins_over_paid_invoice`, probe).
- Unknown plan fails closed (config + governance audit `fail_closed`).
- Failed/expired/cancelled provider events never unlock entitlement (billing state reads only the trusted Sprint 30 `collection_state`).
- Redactor drops password/secret/token/signature/phone/email/owner_name/card/NIK and nested payloads (`test_redactor_drops_secrets_and_pii`).
- No tenant/public route mutates entitlement state; admin routes are read-only and `platform.admin`.

## Regression confirmations (Sprint 24–31)

Prior gates green in smoke + CI: `billing:go-no-go`, `payment-gateway:go-no-go`, `tenant-plan:go-no-go`, `tenant-lifecycle:go-no-go`, `export-governance:go-no-go`, `usage-ledger:go-no-go`, `report-export-metering:go-no-go`. Full backend suite (Sprint 0–31 + Sprint 32) green.

## Test / smoke / CI results

- Backend: `php artisan test` → **1137 passed, 30558 assertions**.
- Sprint 32 suite: 25 passed, 88 assertions.
- Smoke: `scripts/sprint32_smoke.sh` → **PASS=82 FAIL=0** (structural + command gates + in-DB enforcement probe + no-secret/PII scan + prior gates).
- Governance audit: `entitlement:governance-audit` → PASS.
- Gate: `entitlement:go-no-go` → GO (all signals PASS once docs present).
- CI: `.github/workflows/sprint32-ci.yml` (foundation+smoke; backend gate+regression on PHP 8.5 sqlite; Android build+unit on JDK 21).

## Security / PII / secret redaction

No secrets/PII in config, audit metadata, command output, smoke output, docs, API responses, or test output. Redaction verified by unit test and smoke grep. `entitlement_governance.php` and `pos_foundation.php` contain no credentials.

## Rollback

See architecture doc "Rollback". Additive; drop table + config + services/middleware/commands/routes to revert; prior sprints unaffected.

## Deferred items / risks

- Tenant-facing CRUD routes for branch/user/cashier/outlet/register do not exist yet; limits enforced at the service layer until they do.
- Per-alias distinct caps (separate cashier/outlet keys) can be added to `config/tenant_plan.php` later without changing the Sprint 32 gate.
- VPS deploy not performed (no credentials provided).
