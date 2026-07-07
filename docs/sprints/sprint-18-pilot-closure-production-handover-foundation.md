# Sprint 18 — Pilot Closure & Production Handover Foundation

## Objective

Establish the **pilot closure & production handover** foundation:
Pilot Stabilization → Final Defect/Risk Review → Closure Sign-off → Production
Handover Pack → Support/SLA Handover → Ownership Matrix → Production
GO/WATCH/NO-GO. Implementation-heavy governance; **no new business feature
expansion, no auto production deploy, no real alert sending**.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`
- `docs/PROJECT_RULES.md`
- Sprint 0–17 evidence docs and their GO tags.

## Previous Sprint Foundation Lock

Sprint 0–17 rules remain intact in `docs/PROJECT_RULES.md`; the Foundation Lock
Index now lists sprint-18. Sprint 17 GO tag
`sprint-17-pilot-stabilization-defect-burndown-foundation-go` is the branch base.

## Scope

Backend closure/handover persistence + services + admin APIs + commands + config
+ docs + tests + smoke + CI. Android: no new UI — build/test/release readiness
remain green. Docs-only is not accepted; migrations/models/services/commands/
tests/smoke/CI are all delivered.

## Graphify Summary

- Reused: Sprint 17 `PilotDefect` register + `DefectBurnDownService` +
  `SlaBreachDetectionService`; Sprint 11 `AdminAuditLog` + `platform.admin`;
  Sprint 13–17 release/pilot commands (gate references).
- New: `pilot_closure_runs`, `production_handover_packages`,
  `production_handover_signoffs`; 6 `App\Services\Handover\*` services; 4 admin
  controllers; 4 artisan commands; `config/production_handover.php`; 10
  `docs/handover/*`.
- Gate flow: closure → handover package → sign-offs → production go/no-go
  aggregating all prior-sprint gates by command registration.

## Database Implementation

- `pilot_closure_runs` — closure_reference (unique), status, decision, window,
  final_defect_summary/accepted_risk_summary/handover_readiness_summary (json),
  checklist, evidence_references, created_by/approved_by/approved_at, metadata.
- `production_handover_packages` — handover_reference (unique),
  pilot_closure_run_id, status, decision, candidate_commit/tag, readiness/
  operator/admin/support-sla/backup-restore summaries + ownership_matrix (json),
  checklist, evidence_references, actors, metadata.
- `production_handover_signoffs` — signoff_reference (unique), package fk,
  signer_user_id/name/role, decision, notes, evidence_reference, signed_at.
  Append-only (never deleted).

## Models and Relationships

- `PilotClosureRun` hasMany `ProductionHandoverPackage`; belongsTo creator/approver.
- `ProductionHandoverPackage` belongsTo closure run; hasMany signoffs.
- `ProductionHandoverSignoff` belongsTo package/signer. Status + decision + role
  constants provided.

## Services

- **PilotClosureService** — `evaluate()` combines final defect review, accepted-
  risk review, and Sprint 17 stabilization burn-down into GO/WATCH/NO_GO +
  checklist; `create/approve/block` persist.
- **ProductionHandoverService** — evaluates the handover documentation contract
  (doc presence + ownership-matrix table); `create/update/markReady/
  markHandedOver` with conservative transitions.
- **FinalDefectReviewService** — aggregates defects by severity/status/area,
  unresolved blocking, SLA-breached, retest/verify state → decision.
- **AcceptedRiskFinalReviewService** — detects expired/incomplete acceptance,
  preserves original severity → WATCH/NO_GO.
- **ProductionSignoffService** — append-only sign-offs; latest-per-role summary;
  rejected ⇒ NO_GO, approved-with-risk/pending ⇒ WATCH.
- **ProductionHandoverGoNoGoService** — aggregates all prior-sprint gates
  (command registration), docs, defect review, accepted-risk review, latest
  closure, latest package, and sign-off summary → GO/WATCH/NO_GO.

## Admin Closure/Handover APIs

Behind `auth:sanctum` + `platform.admin` (`/api/v1/admin`): pilot-closures
(index/store/show/approve/block), production-handovers (index/store/show/patch/
mark-ready/mark-handed-over), signoffs (index/store), and read-only
production-handover-go-no-go. Tenant users get 403; unauthenticated get 401.
Every mutation is audit-logged.

## Artisan Commands

`pilot:closure-check`, `production:handover-summary`, `production:signoff-summary`,
`production:handover-go-no-go` — all support `--json` and `--strict`, are
secret-safe, never deploy, never send alerts, never run Gradle.

## Documentation

`docs/handover/`: pilot-closure-checklist, production-handover-pack,
operator-admin-handover, final-defect-closure-summary, accepted-risk-final-review,
production-readiness-signoff, backup-restore-handover, support-sla-handover,
release-ownership-matrix, production-go-watch-no-go-report.

## Android CI Evidence

No Android runtime change. `scripts/android_release_readiness.sh` unchanged;
`sprint18-ci.yml` runs `assembleDebug` + `testDebugUnitTest` on JDK 21. Package
`com.aishtech.poslite`, minSdk 26, targetSdk 35 asserted. No Android
admin/handover/production UI added.

## Gate Evidence

`sprint18-ci.yml` runs, in separate jobs: Sprint 18 smoke, backend tests, the
release gate, RC/UAT gate, deployment/field gate, monitoring/hypercare gate,
stabilization/defect gate, the new closure/handover gate, and Android build/test.

## No Business Feature Expansion / No Auto Deploy / No Real Alerts

No new business module, cashier workflow, QRIS behavior, inventory/report
feature. No production deploy automation. No Slack/WhatsApp/email sending. The
security scan test enforces no outbound HTTP/notification in handover services.

## Application Rules Update

`docs/PROJECT_RULES.md` gains the Sprint 18 runtime rule and the lock index now
lists sprint-18. `config/pos_foundation.php` lists `sprint_18` and the new rule
flags. Sprint 0–17 rules preserved.

## Testing Evidence

Feature tests: PilotClosureServiceTest, ProductionHandoverServiceTest,
FinalDefectReviewServiceTest, AcceptedRiskFinalReviewServiceTest,
ProductionSignoffServiceTest, ProductionHandoverGoNoGoServiceTest,
ProductionHandoverAdminApiTest, ProductionHandoverCommandsTest,
ProductionHandoverSecurityScanTest, ProductionHandoverRegressionRouteTest, plus
the full Sprint 0–17 suite.

## Validation Commands

```bash
bash scripts/sprint18_smoke.sh
bash scripts/android_release_readiness.sh
cd backend && php artisan test
cd backend && php artisan production:readiness-check --json
cd backend && php artisan release:go-no-go --json
cd backend && php artisan pilot:rc-check --json
cd backend && php artisan pilot:uat-summary --json
cd backend && php artisan pilot:deployment-check --json
cd backend && php artisan pilot:field-trial-summary --json
cd backend && php artisan pilot:daily-monitoring-check --json
cd backend && php artisan pilot:health-summary --json
cd backend && php artisan hypercare:issue-triage --json
cd backend && php artisan pilot:stabilization-go-no-go --json
cd backend && php artisan pilot:closure-check --json
cd backend && php artisan production:handover-summary --json
cd backend && php artisan production:signoff-summary --json
cd backend && php artisan production:handover-go-no-go --json
```

## Validation Results

Recorded in the PR / CI run. Backend tests green; smoke green; Android build/test
green in CI; all gate commands emit valid JSON with no secrets.

## GO Criteria

See §12 of the Sprint 18 brief — all 53 criteria satisfied (persistence, 6
services, admin APIs behind platform.admin, tenant 403, append-only sign-offs,
rejected ⇒ NO_GO, approved-with-risk ⇒ WATCH, 4 commands with `--json`, 10 docs,
prior-sprint commands intact, Android readiness intact, CI gate wired, no
forbidden files, PR merged, GO tag).

## No-Go Checks

None triggered — rules intact, tables/services/commands/docs present, tenant
isolation enforced, no secrets/APK/AAB/keystore committed, working tree clean.

## Follow-up for Sprint 19

Post-handover production operations: live incident management, real (opt-in)
alerting integration behind config, and first production release retro.
