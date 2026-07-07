# Sprint 16 — Pilot Monitoring & Hypercare Foundation

## Objective

Establish the pilot monitoring & hypercare foundation:

> Field Trial Ready App → Daily Monitoring → Health Signals → Hypercare Issue
> Triage → SLA → Operator Feedback → GO/WATCH/NO-GO Hypercare Report

This is a monitoring/hypercare governance sprint, not a business-feature sprint.
No new business module, no automatic production deploy, no real alert sending.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`
- `docs/PROJECT_RULES.md`
- Sprint 0–15 evidence docs under `docs/sprints/`
- Sprint 13 release docs, Sprint 14 RC/UAT docs, Sprint 15 deployment/field docs
  under `docs/release/` and `docs/pilot/`

## Previous Sprint Foundation Lock

Foundation Lock Index updated to include
`docs/sprints/sprint-16-pilot-monitoring-hypercare-foundation.md`. All Sprint
0–15 runtime rules retained; Sprint 16 runtime rule appended.

## Scope

In scope: monitoring/hypercare services, commands, config, docs, tests, smoke,
CI gate. Out of scope: new business features, real monitoring SaaS integration,
real Slack/WhatsApp/email alerts, Play Store deploy, app signing keys, APK/AAB,
Android monitoring/hypercare UI, destructive DB resets, production data mutation.

## Graphify Summary

- Reused Sprint 13 `ReleaseGateService` / `ProductionReadinessService`, Sprint 14
  `PilotReleaseCandidateService` / `OperatorUatSummaryService`, Sprint 15
  `PilotDeploymentReadinessService` / `FieldTrialEvidenceService` patterns.
- New pilot services mirror the established GO/WATCH/NO-GO deterministic shape.
- Commands mirror the `--json` / `--strict` contract and exit-code convention.
- Docs slot into `docs/pilot/`; CI mirrors `sprint15-ci.yml` plus a new
  monitoring/hypercare gate job.
- GO tag depends on `sprint16-ci` green (foundation+smoke, backend tests,
  release gate, RC/UAT gate, deployment/field gate, monitoring/hypercare gate,
  Android build/test).

## Backend Implementation

- `backend/config/pilot_monitoring.php` — required docs, required commands,
  Android script, required/critical signals, health areas, severity levels, SLA
  targets, open-issue statuses, optional result files. No secrets, no real data.
- `backend/config/pos_foundation.php` — added `sprint_16` and monitoring/hypercare
  rule flags.

### PilotMonitoringService

`app/Services/Pilot/PilotMonitoringService.php` — emits a signal per canonical
daily monitoring signal plus doc-presence, release/pilot command, and Android
readiness signals. Blocking-signal FAIL → NO-GO; any WARN/non-blocking FAIL →
WATCH; else GO. Never prints secrets, never runs Gradle, never mutates data.

### PilotHealthSummaryService

`app/Services/Pilot/PilotHealthSummaryService.php` — aggregates 12 canonical
health areas into PASS/WARN/FAIL counts and a GO/WATCH/NO-GO decision.

### HypercareIssueTriageService

`app/Services/Pilot/HypercareIssueTriageService.php` — canonical severity levels
+ SLA targets; counts open issues by severity; open BLOCKER/CRITICAL → NO-GO,
open MAJOR → WATCH, MINOR/TRIVIAL/ACCEPTED_RISK → GO.

## Artisan Commands

- `pilot:daily-monitoring-check` — daily monitoring signals → GO/WATCH/NO-GO.
- `pilot:health-summary` — health-area aggregation → GO/WATCH/NO-GO.
- `hypercare:issue-triage` — severity triage → GO/WATCH/NO-GO.

All support `--json` and `--strict`; exit 0 on GO/WATCH (non-strict), 1 on NO-GO
or strict WATCH. None print secrets, mutate data, run Gradle, or send alerts.

## Daily Monitoring Runbook

`docs/pilot/daily-monitoring-runbook.md` — schedule placeholder + all monitoring
signals + issue register review + daily GO/WATCH/NO-GO decision.

## Hypercare Issue Triage Workflow

`docs/pilot/hypercare-issue-triage-workflow.md` — intake, severity, blocking,
owner, SLA, status, retest, accepted-risk, escalation.

## Severity/SLA Rules

`docs/pilot/field-issue-severity-sla.md` — severity/SLA/decision-impact table.

## Operator Feedback Log

`docs/pilot/operator-feedback-log.md` — anonymized/placeholder feedback table
(no passwords/secrets/private customer data).

## Pilot Health Summary Template

`docs/pilot/pilot-health-summary-template.md` — per-day health summary.

## Hypercare GO/WATCH/NO-GO Report

`docs/pilot/hypercare-go-watch-no-go-report.md` — evidence-backed hypercare
decision.

## Failed Sync Monitoring

`docs/pilot/failed-sync-monitoring-checklist.md`.

## Payment/QRIS Monitoring

`docs/pilot/payment-qris-monitoring-checklist.md`.

## Device/Subscription Anomaly Monitoring

`docs/pilot/device-subscription-anomaly-checklist.md`.

## Closing/Report Monitoring

`docs/pilot/closing-report-monitoring-checklist.md`.

## Android CI Evidence

`sprint16-ci.yml` runs `assembleDebug` + `testDebugUnitTest` on JDK 21 (not
optional, no `continue-on-error`). Local Android build cannot run (no Gradle /
JDK 25 locally); CI is the authoritative build gate. Package `com.aishtech.poslite`,
minSdk 26, targetSdk 35 unchanged. No new Android UI added.

## Release Gate Evidence

`production:readiness-check` and `release:go-no-go` still run (Sprint 13 gate job).

## RC/UAT Gate Evidence

`pilot:rc-check` and `pilot:uat-summary` still run (Sprint 14 gate job).

## Deployment/Field Gate Evidence

`pilot:deployment-check` and `pilot:field-trial-summary` still run (Sprint 15 gate
job).

## No Business Feature Expansion Decision

No new business module/API/workflow. Signals and areas reference existing
Sprint 2–15 behavior only.

## No Auto Production Deploy Decision

No deployment automation added; commands are read-only, deterministic gates.

## No Real Alert Sending Decision

No Slack/WhatsApp/email integration; monitoring output is CLI/JSON only.

## Application Rules Update

`docs/PROJECT_RULES.md` — Foundation Lock Index extended to Sprint 16; Sprint 16
runtime rule appended; Sprint 0–15 rules retained.

## Optional Database Persistence

Deferred. Sprint 16 foundation uses deterministic services/commands/docs first;
`pilot_monitoring_runs` / `hypercare_issue_snapshots` persistence can be Sprint
17+. Rationale: keeps the gate deterministic and CI-safe without production data.

## Testing Evidence

Backend tests (`backend/tests/Feature`):

- `PilotMonitoringServiceTest`
- `PilotDailyMonitoringCheckCommandTest`
- `PilotHealthSummaryServiceTest`
- `PilotHealthSummaryCommandTest`
- `HypercareIssueTriageServiceTest`
- `HypercareIssueTriageCommandTest`
- `PilotMonitoringSecurityScanTest`
- `PilotMonitoringRegressionRouteTest`

## Backend Compatibility Evidence

`PilotMonitoringRegressionRouteTest` asserts Sprint 0–15 routes + all
release/pilot/monitoring commands remain registered.

## Validation Commands

```bash
bash scripts/sprint16_smoke.sh
bash scripts/android_release_readiness.sh
cd backend && php artisan test
cd backend && php artisan pilot:daily-monitoring-check --json
cd backend && php artisan pilot:health-summary --json
cd backend && php artisan hypercare:issue-triage --json
```

## Validation Results

Recorded in the PR / CI run for `sprint16-ci` (all jobs green required before GO
tag).

## GO Criteria

See task GO criteria 1–37: foundation intact, Sprint 0–16 rules locked, 3
services + 3 commands present, 10 monitoring docs present, `--json` supported,
secret-safe, all prior gates still run, Android CI green, smoke pass, backend
tests pass, no forbidden files, PR merged, GO tag exact-match to main HEAD.

## No-Go Checks

Any missing service/command/doc/rule, any secret print, any production mutation,
any real alert, any broken prior gate, any forbidden file, red CI, or failing
tests block the GO tag.

## Follow-up for Sprint 17

- Optional monitoring-run / issue-snapshot persistence tables + models.
- Optional structured trend aggregation across pilot days.
