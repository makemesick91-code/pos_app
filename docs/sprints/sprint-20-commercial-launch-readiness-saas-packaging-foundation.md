# Sprint 20 — Commercial Launch Readiness & SaaS Packaging Foundation

## Objective

Establish the commercial launch readiness & SaaS packaging foundation:
Production Operations GO → Commercial Package Catalog → Pricing/Plan Governance →
Sales Enablement → Onboarding Capacity → Launch Sign-off → Commercial Launch
GO/WATCH/NO-GO. This is a governance sprint — **not** a new business feature, and
**not** a marketing/public-website sprint.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`
- `docs/PROJECT_RULES.md`
- Sprint 0–19 evidence docs and GO tags.

## Previous Sprint Foundation Lock

Builds on Sprint 19 (Production Operations Baseline & Post-Handover Governance).
Main at `dc1d9c7`, tagged
`sprint-19-production-operations-post-handover-governance-foundation-go`.

## Scope

Commercial launch governance only. No public signup, no real billing collection,
no subscription payment automation, no public marketing/pricing page, no new
business feature, no auto production deploy, no real alert sending, no
secrets/APK/AAB/keystore committed. See
[no-public-signup-no-real-billing-policy](../commercial/no-public-signup-no-real-billing-policy.md).

## Graphify Summary

Commercial launch readiness reuses: Sprint 10 subscription/device foundation
(runtime enforcement stays authoritative), Sprint 11 admin SaaS + AdminAuditLog,
Sprint 12 onboarding/demo data, and the cumulative Sprint 13–19 release/pilot/
handover/operations gate commands. New commercial layer sits admin-only on top and
never bypasses subscription/device rules.

## Database Implementation

- `commercial_launch_runs` — launch run + aggregate summaries + GO/WATCH/NO_GO.
- `saas_package_catalogs` — package catalog (governance metadata only).
- `commercial_launch_signoffs` — preserved sign-off records.
- `commercial_launch_risks` — commercial risk register with accepted-risk governance.

## Models and Relationships

- `CommercialLaunchRun` — creator/approver (User), hasMany signoffs & risks.
- `SaasPackageCatalog` — creator/approver (User), `active()` scope.
- `CommercialLaunchSignoff` — belongsTo run & signer.
- `CommercialLaunchRisk` — belongsTo run, owner, acceptedRiskBy; `open()` scope.

## Services

- **CommercialLaunchReadinessService** — aggregates package/pricing/sales/
  onboarding/risk/signoff readiness + docs into GO/WATCH/NO_GO; owns launch run
  create/approve/block and signoff recording.
- **SaaSPackageCatalogService** — package create/update/approve/retire + summary.
- **PricingPlanGovernanceService** — package pricing vs SubscriptionPlan.
- **SalesEnablementReadinessService** — sales enablement docs contract.
- **OnboardingCapacityService** — weekly capacity vs active package levels.
- **CommercialRiskGovernanceService** — risk create/update/accept/close + summary.
- **CommercialLaunchGoNoGoService** — prior gates + commercial readiness aggregate.

All free-text/metadata is secret-sanitized via `SanitizesCommercialText`.

## Admin Commercial APIs

Under `/api/v1/admin/*` behind `auth:sanctum` + `platform.admin`:
commercial-launch-runs (+approve/block/signoffs), saas-packages
(+approve/retire), commercial-risks (+accept-risk/close),
commercial-launch-readiness, commercial-package-summary,
commercial-onboarding-capacity, commercial-launch-go-no-go. Every mutation is
audit-logged. Tenant/unauthenticated users are blocked.

## Artisan Commands

- `commercial:launch-readiness`
- `commercial:package-summary`
- `commercial:onboarding-capacity`
- `commercial:launch-go-no-go`

All support `--json` and `--strict`, and are secret-safe.

## Commercial Documentation

- [commercial-launch-checklist](../commercial/commercial-launch-checklist.md)
- [saas-package-catalog](../commercial/saas-package-catalog.md)
- [pricing-plan-governance](../commercial/pricing-plan-governance.md)
- [sales-enablement-pack](../commercial/sales-enablement-pack.md)
- [customer-onboarding-capacity](../commercial/customer-onboarding-capacity.md)
- [commercial-risk-register](../commercial/commercial-risk-register.md)
- [launch-signoff](../commercial/launch-signoff.md)
- [commercial-go-watch-no-go-report](../commercial/commercial-go-watch-no-go-report.md)
- [no-public-signup-no-real-billing-policy](../commercial/no-public-signup-no-real-billing-policy.md)

## Gate Evidence

Sprint 20 CI (`.github/workflows/sprint20-ci.yml`) runs: Sprint 20 smoke, backend
tests, release gate, RC/UAT gate, deployment/field gate, monitoring/hypercare
gate, stabilization/defect gate, closure/handover gate, production operations
gate, commercial launch gate (`commercial:launch-readiness`,
`commercial:package-summary`, `commercial:onboarding-capacity`,
`commercial:launch-go-no-go`), Android `assembleDebug` + `testDebugUnitTest`.

## No-Expansion Decisions

- **No business feature expansion** — governance only.
- **No public signup** — admin-only onboarding.
- **No real billing collection** — pricing is metadata.
- **No auto production deploy** — reporting only.
- **No real alert sending** — no outbound Http::post/notify in services.

## Application Rules Update

`docs/PROJECT_RULES.md` updated: Foundation Lock Index extended to Sprint 20 and a
new "Sprint 20 Commercial Launch Readiness & SaaS Packaging Foundation Runtime
Rule" section added. `backend/config/pos_foundation.php` extended with `sprint_20`
and commercial rules.

## Testing Evidence

- `SaaSPackageCatalogServiceTest`, `CommercialRiskGovernanceServiceTest`,
  `CommercialLaunchReadinessServiceTest`, `CommercialLaunchGoNoGoServiceTest`.
- `CommercialLaunchAdminApiTest` (admin access + tenant/unauth denial).
- `CommercialLaunchCommandsTest` (commands + `--json`).
- `CommercialLaunchSecurityScanTest` (no secrets/alerts/deploy/APK/AAB).
- `CommercialLaunchRegressionRouteTest` (prior-sprint routes intact).

## Validation Commands

```bash
bash scripts/sprint20_smoke.sh
cd backend && php artisan test
php artisan commercial:launch-readiness --json
php artisan commercial:package-summary --json
php artisan commercial:onboarding-capacity --json
php artisan commercial:launch-go-no-go --json
bash scripts/android_release_readiness.sh
```

## GO Criteria

Implementation complete; backend tests pass; Android CI green; all prior gates
run; commercial launch gate present; rules locked through Sprint 20; smoke pass;
no forbidden files; PR merged; GO tag
`sprint-20-commercial-launch-readiness-saas-packaging-foundation-go` pushed.

## No-Go Checks

No public signup / real billing / payment automation / marketing page / new
business feature / auto deploy / real alerts / secrets / APK / AAB / keystore.

## Follow-up for Sprint 21

Post-launch commercial operations, real billing integration design (deferred),
and public onboarding funnel design (deferred) — all out of scope until a future
sprint explicitly updates the foundation.
