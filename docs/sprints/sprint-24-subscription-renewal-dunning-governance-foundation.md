# Sprint 24 — Subscription Renewal & Dunning Governance Foundation

## Objective

Establish a manual, admin-governed, evidence-backed subscription **renewal and
dunning** governance foundation over `TenantSubscription`, following the Sprint 23
billing collection governance foundation. No auto-charge, no auto-suspend, no real
reminder sending — every renewal is an explicit manual decision.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`
- `docs/PROJECT_RULES.md`
- Sprint 0–23 evidence docs (`docs/sprints/`)
- Sprint 10 subscription/device foundation; Sprint 23 billing collection governance.

## Previous Sprint Foundation Lock

Builds on Sprint 0–23. Previous GO tag:
`sprint-23-billing-collection-governance-foundation-go`. No prior-sprint behavior is
changed.

## Graphify Summary

- Reused: `TenantSubscription`, `SubscriptionPlan`, `SaasBillingInvoice`/`Account`
  (read-only awareness), `AdminAuditLogger`, `platform.admin` middleware, the
  Sprint 23 service/controller/command/gate patterns.
- New domain namespace: `App\Services\SubscriptionRenewal`.
- Renewal/dunning is strictly separate from POS QRIS/cash customer payments and
  from billing collection invoice/payment-evidence.

## Scope

Manual renewal policy, renewal runs, renewal candidates, manual dunning notices,
renewal decisions (with an explicit manual apply), renewal activities, renewal
risks and sign-offs; admin APIs behind `platform.admin`; four artisan gate
commands; docs; smoke; CI. No forbidden automation (see Guardrails).

## Database Implementation

Eight tables (migrations `2026_07_10_940001`–`940008`):
`subscription_renewal_policies`, `subscription_renewal_runs`,
`subscription_renewal_candidates`, `subscription_dunning_notices`,
`subscription_renewal_decisions`, `subscription_renewal_activities`,
`subscription_renewal_risks`, `subscription_renewal_signoffs`.

## Models

`SubscriptionRenewalPolicy`, `SubscriptionRenewalRun`,
`SubscriptionRenewalCandidate`, `SubscriptionDunningNotice`,
`SubscriptionRenewalDecision`, `SubscriptionRenewalActivity`,
`SubscriptionRenewalRisk`, `SubscriptionRenewalSignoff`.

## Services

`App\Services\SubscriptionRenewal`: `SubscriptionRenewalPolicyService`,
`SubscriptionRenewalRunService`, `SubscriptionRenewalCandidateService`,
`SubscriptionDunningNoticeService`, `SubscriptionRenewalDecisionService`,
`SubscriptionRenewalActivityService`, `SubscriptionRenewalRiskGovernanceService`,
`SubscriptionRenewalReadinessService`, `SubscriptionRenewalGoNoGoService`, plus the
`SanitizesSubscriptionRenewalText` trait.

## Admin APIs

All under `/api/v1/admin/subscription-renewal/*`, `auth:sanctum` + `platform.admin`:
policies, runs (+evaluate/complete), candidates (+ready/grace/overdue/do-not-renew),
dunning-notices (prepare/mark-sent-manually/complete/cancel/skip), decisions
(+void/apply-manual-renewal), activities (+complete/cancel), risks
(+accept-risk/close), sign-offs, readiness, candidate-summary, dunning-summary,
go-no-go.

## Commands

`subscription-renewal:readiness`, `subscription-renewal:candidate-summary`,
`subscription-renewal:dunning-summary`, `subscription-renewal:go-no-go` — all with
`--json` and `--strict`.

## Docs

`docs/subscription-renewal/`: subscription-renewal-policy, dunning-manual-notice-policy,
renewal-lifecycle-map, grace-overdue-governance, manual-renewal-decision-playbook,
subscription-renewal-risk-register, subscription-renewal-go-watch-no-go-report.

## PROJECT_RULES Update

Foundation Lock Index extended through Sprint 24; a Sprint 24 runtime rule block
added; `backend/config/pos_foundation.php` updated with `sprint_24` and the Sprint
24 governance flags.

## README Update

Added the "Sprint 24 — Subscription Renewal & Dunning Governance Foundation"
section. Sprint 0–23 sections retained.

## CI Update

`.github/workflows/sprint24-ci.yml`: foundation & smoke, backend tests, Sprint
13–23 prior gates, subscription renewal gate, and Android assembleDebug +
testDebugUnitTest on JDK 21.

## Tests

`SubscriptionRenewal*ServiceTest` (9), `SubscriptionRenewalAdminApiTest`,
`SubscriptionRenewalCommandsTest`, `SubscriptionRenewalSecurityScanTest`,
`SubscriptionRenewalRegressionRouteTest`.

## Android Compatibility

No Android source changed. No renewal/dunning/admin UI added. Package/SDK intact.

## Guardrails

No real payment gateway, no auto-charge, no subscription payment automation, no
auto tenant suspension/reactivation, no auto subscription renewal without a manual
decision, no auto plan/device-limit change, no public renewal portal / payment
link, no real email/WhatsApp/SMS/Slack/CRM/accounting integration. Dunning is a
manual queue. Payment evidence never auto-renews. Apply-manual-renewal is explicit,
admin-only, audit-logged.

## Validation Commands

```
php artisan migrate:fresh
php artisan test --filter SubscriptionRenewal
php artisan subscription-renewal:readiness --json
php artisan subscription-renewal:candidate-summary --json
php artisan subscription-renewal:dunning-summary --json
php artisan subscription-renewal:go-no-go --json
bash scripts/sprint24_smoke.sh
```

## Validation Results

Recorded at merge time; backend tests pass, smoke passes, Android CI green.

## GO Criteria

Backend tests pass, Android CI assembleDebug + testDebugUnitTest pass, Sprint 13–23
gates pass, subscription renewal readiness/go-no-go run clean and secret-free, PR
merged, GO tag pushed.

## No-Go Checks

Any enabled automation guardrail, missing doc/command, open CRITICAL/HIGH risk
without a valid accepted risk, rejected sign-off, or any forbidden automation
introduced.

## Follow-up for Sprint 25

Renewal reporting/analytics, optional templated (still manual) notice content, and
deeper billing-collection ↔ renewal correlation — all remaining manual-first.
