# Sprint 22 — Lead Management / Sales Pipeline Readiness Foundation

## Objective

Establish an internal, admin-governed **sales pipeline readiness foundation** on top
of the Sprint 21 public website lead interest flow: sales lead persistence, pipeline
stages, activities, assignments, risks, signoffs, admin APIs, sales pipeline
services, `sales-pipeline:*` commands, docs, smoke, and CI — with a strict guardrail
that a lead **never** auto-creates a tenant/user/subscription/device, never bills,
never integrates a real CRM, and never sends real messages.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`
- `docs/PROJECT_RULES.md`
- Sprint 0–21 evidence docs under `docs/sprints/`
- Sprint 21 public website lead interest (`lead_interest_submissions`)

## Previous Sprint Foundation Lock

Built on `main` at the Sprint 21 GO tag
`sprint-21-public-website-landing-page-readiness-foundation-go`. All Sprint 0–21
behavior remains intact; this sprint is additive.

## Graphify Summary

- Relevant existing surfaces: `LeadInterestSubmission` model/service (Sprint 21),
  `platform.admin` guard + `AdminAuditLogger` (Sprint 11), the cumulative
  `production:*` / `pilot:*` / `commercial:*` / `public-website:*` gate commands.
- Sprint 22 mirrors the Sprint 20/21 governance vertical (model → migration →
  service → request → controller → resource → command → routes → tests).
- No-go guardrails: no auto tenant/user/subscription/device from a lead; no real
  billing/CRM/messaging; no Android business-flow or UI change.

## Scope

New runtime: migrations, models, services, admin controllers, requests, resources,
commands, config, docs, tests, smoke, CI, PROJECT_RULES + README updates. No public
self-service signup, no auto provisioning, no real billing/CRM/messaging, no Android
POS business feature, no Android sales/admin UI.

## Database Implementation

Migrations (`backend/database/migrations/2026_07_09_920001..920006`):

- `sales_pipeline_stages`
- `sales_leads` (nullable FK → `lead_interest_submissions`, `sales_pipeline_stages`)
- `sales_lead_activities`
- `sales_lead_assignments`
- `sales_pipeline_risks`
- `sales_pipeline_signoffs`

## Models

`SalesPipelineStage`, `SalesLead`, `SalesLeadActivity`, `SalesLeadAssignment`,
`SalesPipelineRisk`, `SalesPipelineSignoff` (status/severity/role/decision
constants). `AdminAuditLog` extended with Sprint 22 action/target constants.

## Services

`App\Services\SalesPipeline\`:

- `SanitizesSalesPipelineText` (trait) — secret redaction.
- `SalesLeadIntakeService` — manual create + import-from-interest (idempotent).
- `SalesPipelineStageService` — stages + ensure-defaults + lead transition.
- `SalesLeadActivityService` — activities (manual-note only).
- `SalesLeadAssignmentService` — assign/reassign/unassign with history.
- `SalesQualificationService` — advisory score + qualify/lost/ready-for-onboarding.
- `SalesPipelineRiskGovernanceService` — risk lifecycle + GO/WATCH/NO_GO.
- `SalesPipelineReadinessService` — aggregate readiness + signoffs.
- `SalesPipelineGoNoGoService` — prior gates + readiness aggregation.

## Admin APIs

Under `/api/v1/admin`, middleware `auth:sanctum` + `platform.admin`:
`sales-pipeline/stages*`, `sales-leads*` (incl. `import-interest`, `transition`,
`qualify`, `mark-lost`, `ready-for-onboarding`, activities, assign/unassign),
`sales-pipeline/risks*`, `sales-pipeline/signoffs`, and read-only `readiness`,
`lead-summary`, `activity-summary`, `go-no-go`.

## Commands

`sales-pipeline:readiness`, `sales-pipeline:lead-summary`,
`sales-pipeline:activity-summary`, `sales-pipeline:go-no-go` — all support
`--json` / `--strict`.

## Docs

`docs/sales-pipeline/`: lead-management-policy, sales-pipeline-stage-map,
qualification-readiness-checklist, manual-follow-up-playbook,
onboarding-handover-readiness, sales-pipeline-risk-register,
sales-pipeline-go-watch-no-go-report.

## PROJECT_RULES Update

Foundation Lock Index extended through Sprint 22; new "Sprint 22 Lead Management /
Sales Pipeline Readiness Foundation Runtime Rule" section added.
`backend/config/pos_foundation.php` gains `sprint_22` + Sprint 22 rule flags.

## README Update

New "Sprint 22 — Lead Management / Sales Pipeline Readiness Foundation" section.

## CI Update

`.github/workflows/sprint22-ci.yml` — jobs: foundation-and-smoke, backend-tests
(PHP 8.5), prior-sprint-gates-13-21, sales-pipeline-gate, android-build-test
(JDK 21, assembleDebug + testDebugUnitTest).

## Tests

`SalesLeadIntakeServiceTest`, `SalesPipelineStageServiceTest`,
`SalesLeadActivityServiceTest`, `SalesLeadAssignmentServiceTest`,
`SalesQualificationServiceTest`, `SalesPipelineRiskGovernanceServiceTest`,
`SalesPipelineReadinessServiceTest`, `SalesPipelineGoNoGoServiceTest`,
`SalesPipelineAdminApiTest`, `SalesPipelineCommandsTest`,
`SalesPipelineSecurityScanTest`, `SalesPipelineRegressionRouteTest`.

## Android Compatibility

No Android change. `com.aishtech.poslite`, minSdk 26, targetSdk 35 intact;
`assembleDebug` + `testDebugUnitTest` remain green in CI. No sales/admin UI added.

## Guardrails

No auto tenant/user/subscription/device from a lead; no real billing collection; no
real CRM integration; no real WhatsApp/email/Slack sending; manual-follow-up-only;
ready-for-onboarding = manual review; admin APIs behind `platform.admin`; tenant
users blocked; mutations audit-logged; resources/commands secret-safe.

## Validation Commands

See section 17 of the sprint brief: `bash scripts/sprint22_smoke.sh`,
`php artisan migrate --force`, `php artisan test`, the prior-gate commands, and the
four `sales-pipeline:*` commands.

## Validation Results

- `php -l` clean on all new files; `php artisan route:list` registers all Sprint 22
  routes; `sales-pipeline:*` commands run; migrations apply on sqlite.
- Full `php artisan test` + CI results captured at PR/merge time.

## GO Criteria

Merged to `main`; Sprint 22 CI green (backend tests, prior Sprint 13–21 gates, sales
pipeline gate, Android assembleDebug + testDebugUnitTest, smoke, forbidden-files
scan); working tree clean. Then tag
`sprint-22-lead-management-sales-pipeline-readiness-foundation-go`.

## No-Go Checks

Any of: CI missing/failed, backend/Android tests fail, prior gates fail, sales
pipeline gate fail, PROJECT_RULES missing Sprint 22 rule/index, admin APIs not
protected, a lead auto-provisions, real billing/CRM/messaging added, Android UI
added, secret/artifact committed.

## Follow-up for Sprint 23

Explicit, human-approved "convert ready lead → tenant onboarding run" action reusing
the Sprint 12 onboarding service; formalized retention/consent before any real CRM
or outbound messaging integration.
