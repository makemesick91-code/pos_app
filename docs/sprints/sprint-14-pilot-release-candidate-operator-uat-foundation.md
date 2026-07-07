# Sprint 14 — Pilot Release Candidate & Operator UAT Foundation

## Objective

Establish the pilot release candidate (RC) and operator UAT foundation:
Release-ready app → Pilot RC checklist → Operator UAT scenario pack → Issue
register → RC GO/WATCH/NO-GO evidence → Pilot candidate GO tag. No new business
features, no automatic production deploy.

## Source of Truth

- Foundation: [`docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`](../foundation/POS_ANDROID_SAAS_FOUNDATION.md)
- Governance: [`docs/PROJECT_RULES.md`](../PROJECT_RULES.md)
- Sections observed: 8, 9, 10, 12, 14, 15, 16, 17, 21, 22, 25, 26.

## Previous Sprint Foundation Lock

Sprint 0 → Sprint 13 runtime rules remain intact in `docs/PROJECT_RULES.md`.
The Foundation Lock Index now includes the Sprint 14 evidence document. This
project remains a **multi-tenant Android POS SaaS**, not a single-store POS.

## Scope

Delivered (RC/UAT governance, not business features):

- `PilotReleaseCandidateService`, `OperatorUatSummaryService`
- `pilot:rc-check`, `pilot:uat-summary` Artisan commands
- `config/pilot_uat.php`
- Pilot docs under `docs/pilot/`
- Backend tests (services, commands, security scan, regression routes)
- `scripts/sprint14_smoke.sh`, `.github/workflows/sprint14-ci.yml`
- PROJECT_RULES + README + `pos_foundation.php` lock through Sprint 14

Explicitly out of scope: new business module, new cashier/QRIS/inventory/report
behavior, production deploy automation, Play Store deploy, signing keys, Android
admin/onboarding/UAT UI, real operator credentials, public UAT portal.

## Graphify Summary

- Foundation + Sprint 0–13 evidence and GO tags → prerequisite chain (intact).
- Sprint 13 release services/commands (`ProductionReadinessService`,
  `ReleaseGateService`, `production:readiness-check`, `release:go-no-go`) →
  reused, folded into the pilot RC decision.
- Sprint 11 admin + Sprint 12 onboarding/demo → referenced by smoke + UAT.
- New pilot RC/UAT flow → `pilot:rc-check` (docs, services, commands, routes,
  release gate, UAT summary) and `pilot:uat-summary` (scenarios + issues).
- CI release gate + Android CI → authoritative build/release gates for the GO tag.

## Backend Implementation

- `backend/config/pilot_uat.php` — required docs, release docs, required
  commands, blocking/watch severities, open statuses, canonical scenarios,
  required scenarios, optional UAT result file. No secrets, no real tenant data.

### PilotReleaseCandidateService

`backend/app/Services/Pilot/PilotReleaseCandidateService.php`. Aggregates:
pilot RC/UAT docs, Sprint 13 release docs, release services, pilot/release
commands, regression routes, the Sprint 13 release gate decision, and the
operator UAT summary decision → `GO` / `WATCH` / `NO-GO`. Critical FAIL → NO-GO;
non-critical WARN → WATCH; all PASS → GO. Never prints secrets; never runs
Android Gradle.

### OperatorUatSummaryService

`backend/app/Services/Pilot/OperatorUatSummaryService.php`. Provides the
canonical 15-scenario list and, given a structured result (or on-disk
`uat-result.json`), counts scenario statuses and open issues. Open
BLOCKER/CRITICAL issue or failing scenario → NO-GO; open MAJOR → WATCH;
otherwise GO.

### Artisan Commands

- `pilot:rc-check {--json} {--strict}` — exit 0 on GO/WATCH (1 on strict WATCH),
  1 on NO-GO.
- `pilot:uat-summary {--json} {--strict}` — same exit-code contract.

## Pilot RC Checklist

`docs/pilot/pilot-rc-checklist.md` — source-of-truth, GO-tag chain, backend
tests, Android CI, release readiness, backup/restore, demo onboarding, operator
UAT readiness, issue register, no-secrets, and RC GO/WATCH/NO-GO criteria.

## Operator UAT Checklist

`docs/pilot/operator-uat-checklist.md` — 18-step operator scenario checklist
using placeholders (`DEMO_TENANT_PLACEHOLDER`, `operator@example.test`, no
stored password).

## Smoke Scenario Pack

`docs/pilot/smoke-scenario-pack.md` — SMK-01…SMK-15 covering health, auth,
tenant context, product sync, current stock, cash sale, receipt, offline cash
sync, QRIS status, daily report, closing, subscription, device registration,
admin onboarding, and the release readiness command.

## Issue Register Foundation

`docs/pilot/issue-register.md` — severity (BLOCKER…TRIVIAL), status
(OPEN…ACCEPTED_RISK), gating rules, and a table template. Plus
`docs/pilot/uat-result-template.md` for per-session UAT results.

## RC GO/WATCH/NO-GO Evidence

`docs/pilot/rc-go-watch-no-go-evidence.md` — RC candidate commit, GO tag
candidate, backend/Android/release/pilot evidence, open-issue summary, risk
notes, decision, approver placeholder.

## Android CI Evidence

Sprint 14 CI job `android-build-test` runs `:app:assembleDebug` and
`:app:testDebugUnitTest` on JDK 21. No Android runtime/UI change was made; no
`AdminActivity`/`OnboardingActivity`/`UatActivity` introduced. Package
`com.aishtech.poslite`, `minSdk 26`, `targetSdk 35` unchanged.

## Release Gate Evidence

Sprint 14 CI job `backend-release-gate` runs `production:readiness-check --json`
and `release:go-no-go --json`; job `pilot-rc-uat-gate` runs `pilot:rc-check
--json` and `pilot:uat-summary --json`. All run without exposing secrets.

## No Business Feature Expansion Decision

No new cashier, QRIS, inventory, reporting, or admin business behavior was added.
Only release/UAT governance tooling and documentation.

## No Auto Production Deploy Decision

No deployment automation, Play Store publishing, or signing key handling was
added. Pilot RC authorizes a controlled operator trial only.

## Application Rules Update

`docs/PROJECT_RULES.md` — Foundation Lock Index extended to include the Sprint 14
evidence doc; new "Sprint 14 Pilot Release Candidate & Operator UAT Foundation
Runtime Rule" appended. Sprint 0–13 rules unchanged. `pos_foundation.php` lists
`sprint_14` and pilot gate flags.

## Testing Evidence

Backend feature tests: `PilotReleaseCandidateServiceTest`,
`OperatorUatSummaryServiceTest`, `PilotRcCheckCommandTest`,
`PilotUatSummaryCommandTest`, `PilotReleaseSecurityScanTest`,
`PilotReleaseRegressionRouteTest`. All pilot tests pass; full Sprint 0–14 suite
remains green.

## Backend Compatibility Evidence

`PilotReleaseRegressionRouteTest` asserts the Sprint 0–13 route surface and the
release + pilot commands remain registered. Existing admin, onboarding,
subscription/device, cash, QRIS, receipt, printer, offline sync, inventory,
reports, closing, and release hardening tests remain unchanged and passing.

## Validation Commands

```bash
bash scripts/sprint14_smoke.sh
bash scripts/android_release_readiness.sh
cd backend && composer validate --strict
cd backend && php artisan production:readiness-check --json
cd backend && php artisan release:go-no-go --json
cd backend && php artisan pilot:rc-check --json
cd backend && php artisan pilot:uat-summary --json
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

## Validation Results

Recorded in the PR and in `rc-go-watch-no-go-evidence.md`. Android build/test is
proven by Sprint 14 CI (authoritative build gate).

## GO Criteria

All 28 GO criteria in the Sprint 14 brief — foundation lock, services, commands,
pilot docs, `--json` support, secret-safety, Sprint 13 commands intact, Android
readiness, CI jobs, no business expansion, no auto deploy, no forbidden files,
backend + Android CI green, PR merged, GO tag exact-match to `main` HEAD.

## No-Go Checks

Enumerated in the brief (§9/§19) — any missing service/command/doc, secret leak,
broken Sprint 13 commands, CI not running the pilot/Android gates, failing tests,
production deploy automation, committed secrets/artifacts, or package/SDK
governance drift blocks the GO tag.

## Follow-up for Sprint 15

Sprint 15 — Pilot Deployment & Field Trial Evidence Foundation.
