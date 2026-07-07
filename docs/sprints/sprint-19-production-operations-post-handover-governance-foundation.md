# Sprint 19 — Production Operations Baseline & Post-Handover Governance Foundation

## Objective

Establish the production operations baseline and post-handover governance for the
Aish POS Lite multi-tenant Android POS SaaS: evidence-backed operation runs,
incident governance, backup/restore governance, support/SLA governance,
maintenance-window governance, release/rollback governance, and a single
post-handover production operations GO/WATCH/NO-GO decision. No new business
features, no automatic production deploy, no real alert sending, no real
backup/restore execution.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`
- `docs/PROJECT_RULES.md`
- Sprint 0–18 evidence under `docs/sprints/`
- Sprint 13 release docs, Sprint 16 monitoring docs, Sprint 18 handover docs.

## Previous Sprint Foundation Lock

Sprint 0–18 rules remain intact in `docs/PROJECT_RULES.md`. The Foundation Lock
Index now lists entry 21 (this sprint). The Sprint 19 runtime rule is appended.

## Scope

- **Built:** 3 migrations/models, 7 operations services, admin operations APIs
  behind `platform.admin`, 4 artisan commands, `config/production_operations.php`,
  9 operations docs, 11 backend test classes, a smoke script, and
  `sprint19-ci.yml`.
- **Not built:** new business modules, cashier/QRIS/inventory/reporting features,
  real deploy automation, real alert sending, Play Store deployment, app signing
  keys/keystore/APK/AAB, real credentials/customer data, Android operations panel.

## Graphify Summary

`Production Handover (Sprint 18) → Operation Run (health signals + governance
summaries) → Incident Governance (P0–P4 SLA + accepted risk) → Backup/Restore
Governance (docs/evidence) → Support/SLA Governance (targets + open SLA state) →
Maintenance Governance (rollback-plan-aware) → Release/Rollback Governance
(docs/evidence) → PostHandoverGovernanceReport (aggregate GO/WATCH/NO-GO) → CI
production operations gate → GO tag`. All prior gate commands remain wired.

## Database Implementation

- `production_operation_runs` — reference, status, decision, window, aggregate
  summaries (health/incident/backup/SLA/maintenance/release-rollback), evidence
  references, created/approved actors, metadata.
- `production_incidents` — reference, tenant/store, reporter/assignee, area,
  severity (P0–P4), status, impact, detection/resolution timestamps, SLA due/
  breach, accepted-risk fields, evidence reference, metadata.
- `production_maintenance_windows` — reference, status, risk level, scheduled/
  actual start-end, owner, rollback plan reference, evidence reference, metadata.

## Models and Relationships

- `ProductionOperationRun` belongsTo creator/approver (User).
- `ProductionIncident` belongsTo tenant, store, reporter, assignee, acceptedRiskBy.
- `ProductionMaintenanceWindow` belongsTo owner (User).
- Status/severity/risk constants + scopes + accepted-risk validity helpers.

## ProductionOperationsHealthService

Evaluates the 15 required health signals (schema-contract + governance derived)
into PASS/WARN/FAIL and a GO/WATCH/NO_GO decision (critical FAIL = NO_GO,
non-critical WARN = WATCH, all PASS = GO). `createRun` persists an operation run;
`approve`/`block` record human decisions.

## ProductionIncidentService

Create/update/assign/status-transition/accept-risk; computes SLA due from
severity; detects SLA breaches; summarizes by severity/status/area/SLA. Open
P0/P1 without valid accepted risk = NO_GO; open P2 = WATCH; expired blocking
accepted risk = NO_GO. Secret-like values are sanitised; tenant/store validated.

## BackupRestoreGovernanceService

Verifies the backup/restore governance doc exists and covers required sections
(ownership, frequency, restore rehearsal, rollback, verification) plus supporting
Sprint 13/18 docs. Governance/documentation check only — no real backup/restore.

## SupportSlaGovernanceService

Verifies the support/SLA operations doc, the severity SLA target table, and the
live open-incident SLA state (breached blocking incidents fail). No real alerts.

## MaintenanceWindowService

Create/update/status-transition; a HIGH/CRITICAL active window without a rollback
plan reference forces WATCH/NO-GO. A record never performs a deployment.

## ReleaseRollbackGovernanceService

Verifies the release/rollback governance doc covers required sections (release
candidate, release owner, rollback owner, rollback checklist, validation after
rollback) plus supporting Sprint 13/18 docs. No deploy, no rollback executed.

## PostHandoverGovernanceReportService

Aggregates the cumulative Sprint 13–18 gate contract, the operations docs
contract, ops health, incident summary, backup/restore, support/SLA, maintenance,
and release/rollback governance into a single GO/WATCH/NO-GO decision.

## Admin Operations APIs

Behind `auth:sanctum` + `platform.admin` under `/api/v1/admin`:
operation-runs (index/store/show/approve/block), incidents (index/store/show/
update/assign/status/accept-risk), maintenance-windows (index/store/show/update/
status), plus read-only `production-ops-health`, `production-incident-summary`,
`production-post-handover-go-no-go`. Every mutation is audit-logged. Tenant users
receive 403; unauthenticated receive 401.

## Artisan Commands

- `production:ops-health` — health signals → GO/WATCH/NO-GO.
- `production:incident-summary` — open incidents → GO/WATCH/NO-GO.
- `production:backup-governance-check` — docs/evidence → GO/WATCH/NO-GO.
- `production:post-handover-go-no-go` — aggregate → GO/WATCH/NO-GO.

All support `--json` and `--strict`, print no secrets, and never deploy, run real
backup/restore, or send real alerts.

## Production Operations Runbook

`docs/operations/production-operations-runbook.md` — daily/weekly checklists +
backend/Android/database/payment/offline-sync/subscription/reports health checks
+ escalation placeholder.

## Incident Response Runbook

`docs/operations/incident-response-runbook.md` — intake, P0–P4, impact, owner,
response/resolution targets, accepted-risk handling, communication placeholder.

## Backup/Restore Governance

`docs/operations/backup-restore-governance.md` — ownership, frequency, restore
rehearsal, rollback relation, verification evidence.

## Support/SLA Operations

`docs/operations/support-sla-operations.md` — hours placeholder, P0–P4 table,
escalation owner role, intake, evidence requirements.

## Maintenance Window Governance

`docs/operations/maintenance-window-governance.md` — intake, risk levels,
rollback requirement, approval, evidence, post-maintenance review.

## Release/Rollback Governance

`docs/operations/release-rollback-governance.md` — candidate commit/tag, release
owner, rollback owner, rollback checklist, validation after rollback.

## Production Health Signals

`docs/operations/production-health-signals.md` — the 15 signals + decision rules.

## Post-Handover Governance Report

`docs/operations/post-handover-governance-report.md` — report field template +
aggregated gate references.

## Production Operations GO/WATCH/NO-GO

`docs/operations/production-operations-go-watch-no-go.md` — GO/WATCH/NO-GO
criteria, required evidence/commands/CI gates, safety rules.

## Android CI Evidence

`scripts/android_release_readiness.sh` remains present and is run in Sprint 19 CI.
`sprint19-ci.yml` runs Android `assembleDebug` and `testDebugUnitTest` on JDK 21.
Package `com.aishtech.poslite`, minSdk 26, targetSdk 35 unchanged. No Android
operations/admin UI added.

## Release / RC-UAT / Deployment-Field / Monitoring-Hypercare / Stabilization / Closure-Handover Gate Evidence

Sprint 19 CI re-runs every prior gate command (`production:readiness-check`,
`release:go-no-go`, `pilot:rc-check`, `pilot:uat-summary`, `pilot:deployment-check`,
`pilot:field-trial-summary`, `pilot:daily-monitoring-check`, `pilot:health-summary`,
`hypercare:issue-triage`, `pilot:defect-summary`, `pilot:burndown-summary`,
`pilot:sla-check`, `pilot:stabilization-go-no-go`, `pilot:closure-check`,
`production:handover-summary`, `production:signoff-summary`,
`production:handover-go-no-go`). All remain green.

## No Business Feature Expansion Decision

No new business module, cashier workflow, QRIS provider behavior, or inventory/
reporting feature was added. Sprint 19 is operations governance only.

## No Auto Production Deploy Decision

No deploy automation with credentials was added. Release/rollback is governance
documentation only.

## No Real Alert Sending Decision

No Slack/WhatsApp/email/HTTP alert sending was added. Security scan asserts no
outbound HTTP / notification send in the operations services.

## No Real Backup/Restore Execution Decision

Backup/restore governance is a documentation/evidence check only. No command runs
a real backup or restore or prints DB credentials.

## Application Rules Update

`docs/PROJECT_RULES.md` Foundation Lock Index extended to entry 21; Sprint 19
runtime rule appended (27 mandatory clauses). `config/pos_foundation.php` lists
`sprint_19` and the new operations governance rule flags.

## Testing Evidence

11 backend test classes: `ProductionOperationsHealthServiceTest`,
`ProductionIncidentServiceTest`, `BackupRestoreGovernanceServiceTest`,
`SupportSlaGovernanceServiceTest`, `MaintenanceWindowServiceTest`,
`ReleaseRollbackGovernanceServiceTest`, `PostHandoverGovernanceReportServiceTest`,
`ProductionOperationsAdminApiTest`, `ProductionOperationsCommandsTest`,
`ProductionOperationsSecurityScanTest`, `ProductionOperationsRegressionRouteTest`.

## Backend Compatibility Evidence

The regression test asserts every Sprint 0–18 route/command remains registered.
The full backend suite (Sprint 0–19) passes.

## Validation Commands

```
php artisan migrate --force
php artisan test
php artisan production:ops-health --json
php artisan production:incident-summary --json
php artisan production:backup-governance-check --json
php artisan production:post-handover-go-no-go --json
bash scripts/sprint19_smoke.sh
bash scripts/android_release_readiness.sh
```

## Validation Results

All operations commands return GO in a clean environment. The smoke script and
Android release readiness script pass. The full backend suite passes.

## GO Criteria

See section 12 GO Criteria (1–57) in the Sprint 19 task brief — all satisfied.

## No-Go Checks

All No-Go conditions in the Sprint 19 task brief are avoided: rules intact, all
tables/models/services/commands/docs present, admin APIs behind `platform.admin`,
tenant users blocked, incident/maintenance governance enforced, no secrets/real
alerts/deploy/backup-restore, no forbidden files, Android identity unchanged.

## Follow-up for Sprint 20

- Operational metrics/observability signal integration (still no real alerting).
- Incident event trail table (append-only) if richer history is required.
- Post-incident review (PIR) records linked to operation runs.
