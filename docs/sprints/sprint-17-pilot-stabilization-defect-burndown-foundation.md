# Sprint 17 — Pilot Stabilization & Defect Burn-down Foundation

## Objective

Establish the pilot stabilization and defect burn-down foundation:
Pilot Monitoring → Persistent Defect Register → SLA Breach Detection → Fix
Verification/Retest → Accepted Risk Governance → Burn-down Summary → Stabilization
GO/WATCH/NO-GO. This is a stabilization/governance sprint — **no new business
features**, no auto production deploy, no real alert sending.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`
- `docs/PROJECT_RULES.md`
- Sprint 0–16 evidence docs under `docs/sprints/`
- Sprint 13–16 release/pilot commands, docs, and CI

## Previous Sprint Foundation Lock

Sprint 0–16 rules and the Foundation Lock Index remain intact in
`docs/PROJECT_RULES.md`; Sprint 17 appends its runtime rule and lock index entry 19.

## Scope

- **In:** defect register persistence + event trail, monitoring-run / hypercare-issue
  snapshot persistence, six stabilization services, admin defect APIs behind
  `platform.admin`, four Artisan commands, config, docs, tests, smoke, CI.
- **Out:** new business modules, cashier/QRIS/inventory/reporting feature work, real
  monitoring SaaS integration, real Slack/WhatsApp/email alerts, Play Store deploy,
  signing key/keystore/APK/AAB commits, Android admin/stabilization UI.

## Graphify Summary

Reused: Sprint 11 admin foundation (`platform.admin`, `AdminAuditLogger`,
`AdminAuditLog`), Sprint 16 monitoring/hypercare services + `pilot_monitoring.php`,
Sprint 10 subscription/device gate + base `TestCase`/`TenantFactory`, `User`/`Tenant`/
`Store` models. New: `pilot_defects`, `pilot_defect_events`, `pilot_monitoring_runs`,
`hypercare_issue_snapshots`, and the stabilization service/command/API layer. The
deferred Sprint 16 persistence (`pilot_monitoring_runs`, `hypercare_issue_snapshots`)
is now materialised.

## Database Implementation

Migrations (all under `backend/database/migrations/`):

- `2026_07_07_600000_create_pilot_monitoring_runs_table`
- `2026_07_07_600001_create_hypercare_issue_snapshots_table`
- `2026_07_07_600002_create_pilot_defects_table`
- `2026_07_07_600003_create_pilot_defect_events_table`

`pilot_defects` carries severity, status, blocking flag, reporter/assignee, area,
nullable tenant/store, `sla_due_at`/`sla_breached_at`, accepted-risk fields, fix
verification fields, `evidence_reference`, and `metadata`. `pilot_defect_events` is
append-only.

## Models and Relationships

`PilotMonitoringRun`, `HypercareIssueSnapshot`, `PilotDefect`, `PilotDefectEvent`.
`PilotDefect` belongsTo tenant/store/reporter/assignee/acceptedRiskBy/verifiedBy and
hasMany events; `Tenant`/`Store` hasMany `pilotDefects`; `User` hasMany
`reportedPilotDefects`/`assignedPilotDefects`. Severity/status/area constants live on
`PilotDefect`.

## PilotDefectService

Create/update/assign/transition-status/comment. Appends an immutable event for every
lifecycle change, validates store-belongs-to-tenant, defaults blocking from severity,
computes `sla_due_at`, and sanitises secret-like values from free-text/metadata.

## DefectBurnDownService

Counts by severity/status/area plus open-blocking, SLA-breached, accepted-risk, and
fix-verification counts; produces GO/WATCH/NO-GO.

## SlaBreachDetectionService

Computes SLA due per severity, detects overdue open defects (read-only), and — only via
`markBreaches()` / `--mark-breached` — stamps `sla_breached_at` + appends `SLA_BREACHED`.

## FixVerificationService

`markFixed` → FIXED, `requestRetest` → RETEST, `verify(pass)` → VERIFIED (optionally
CLOSED), `verify(fail)` → IN_PROGRESS. Explicit result, event-tracked.

## AcceptedRiskGovernanceService

Requires approver + reason; requires expiry for BLOCKER/CRITICAL/MAJOR; preserves
original severity + blocking flag; appends `ACCEPTED_RISK`.

## PilotStabilizationReportService

Aggregates burn-down + SLA + docs + command contract + Android script + prior gate
references into a single GO/WATCH/NO-GO.

## Admin Defect APIs

Behind `/api/v1/admin` + `platform.admin`:
`GET/POST /pilot-defects`, `GET/PATCH /pilot-defects/{defect}`, `POST .../assign`,
`.../status`, `.../accept-risk`, `.../mark-fixed`, `.../verify`,
`GET .../events`, `GET /pilot-defect-burndown`, `GET /pilot-stabilization-report`.
Mutations are recorded to `AdminAuditLog`.

## Artisan Commands

`pilot:defect-summary`, `pilot:burndown-summary`, `pilot:sla-check`
(`--mark-breached` optional), `pilot:stabilization-go-no-go`. All support `--json` and
`--strict`, are secret-safe, and never run Android Gradle or send alerts.

## Defect Register Runbook / Burn-down Report / SLA Breach / Accepted Risk / Fix Verification / Stabilization Report

See `docs/pilot/defect-register-runbook.md`, `defect-burndown-report.md`,
`sla-breach-detection.md`, `accepted-risk-governance.md`,
`fix-verification-retest-workflow.md`, `stabilization-go-watch-no-go-report.md`, and
`stabilization-daily-checklist.md`.

## Android CI Evidence

No Android runtime change. `scripts/android_release_readiness.sh`,
`:app:assembleDebug`, and `:app:testDebugUnitTest` run in `sprint17-ci.yml`. Package
`com.aishtech.poslite`, minSdk 26, targetSdk 35 unchanged. No Android
admin/stabilization UI added.

## Release / RC/UAT / Deployment-Field / Monitoring-Hypercare Gate Evidence

`sprint17-ci.yml` re-runs all Sprint 13–16 gate commands (`production:readiness-check`,
`release:go-no-go`, `pilot:rc-check`, `pilot:uat-summary`, `pilot:deployment-check`,
`pilot:field-trial-summary`, `pilot:daily-monitoring-check`, `pilot:health-summary`,
`hypercare:issue-triage`) plus the new stabilization gate.

## No Business Feature Expansion Decision

No cashier/QRIS/inventory/reporting/business behavior was added or modified. Only the
defect governance layer is new.

## No Auto Production Deploy Decision

No CI job deploys to production; the gates are read-only decisions.

## No Real Alert Sending Decision

SLA breach detection and stabilization reporting are governance-only; no Slack/
WhatsApp/email/real alert is sent.

## Application Rules Update

`docs/PROJECT_RULES.md` gains the Sprint 17 runtime rule and Foundation Lock Index
entry 19; `backend/config/pos_foundation.php` gains the Sprint 17 sprint entry and
stabilization rule flags.

## Testing Evidence

`PilotDefectServiceTest`, `PilotDefectAdminApiTest`, `DefectBurnDownServiceTest`,
`SlaBreachDetectionServiceTest`, `FixVerificationServiceTest`,
`AcceptedRiskGovernanceServiceTest`, `PilotStabilizationReportServiceTest`,
`PilotStabilizationCommandsTest`, `PilotStabilizationSecurityScanTest`,
`PilotStabilizationRegressionRouteTest`.

## Backend Compatibility Evidence

Sprint 0–16 suites remain green; the new tables are additive and the base
`TestCase`/`TenantFactory` device/subscription auto-provisioning is unchanged.

## Validation Commands / Results

```bash
bash scripts/sprint17_smoke.sh
bash scripts/android_release_readiness.sh
cd backend && php artisan pilot:defect-summary --json
cd backend && php artisan pilot:burndown-summary --json
cd backend && php artisan pilot:sla-check --json
cd backend && php artisan pilot:stabilization-go-no-go --json
cd backend && php artisan test
```

Results are captured in the PR / CI run.

## GO Criteria

See the Sprint 17 objective checklist: register + event trail + snapshots + six
services + admin APIs + four commands + docs + tests + smoke + CI + rules lock, with
all prior gates still running and Android CI green.

## No-Go Checks

Missing register/event-trail/service/command/API, tenant user reaching admin defect
APIs, accepted risk hiding severity, missing SLA/retest tracking, broken prior gates,
real alerts, or forbidden files committed — any of these blocks the GO tag.

## Follow-up for Sprint 18

Wire a real escalation/alert channel behind config, persist daily monitoring/hypercare
snapshots automatically, and add trend charts to the burn-down report.
