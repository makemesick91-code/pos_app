# Support, Observability & Incident Console — Governance Foundation (UIX-6)

This document records the governance posture for the UIX-6 support,
observability, and incident browser consoles. It complements the enforceable
modular rule `.claude/rules/45-support-observability-incident-governance.md`
(UIX6-R001..UIX6-R033) and the master governance synthesis in
`docs/governance/application-foundation-rules.md`.

## Surface boundaries

| Surface | Guard | Scope | Routes |
|---|---|---|---|
| Platform Admin Support | `platform.admin.web` | Cross-tenant (platform authorization) | `/admin/support`, `/admin/support/tenants`, `/admin/support/tenants/{tenant}` |
| Platform Admin Observability | `platform.admin.web` | Platform-global | `/admin/observability` |
| Platform Admin Incidents | `platform.admin.web` | Platform-global | `/admin/incidents`, `/admin/incidents/{incident}` |
| Tenant Owner Support | `owner` guard + `tenant.owner.web` | Own tenant only | `/owner/support`, `/owner/support/incidents/{incident}` |

The `owner` session guard and platform-admin `web` guard are independent: a
platform-admin session can never reach `/owner/*`, and an owner session can never
reach `/admin/*` (UIX6-R005/R006). A Sanctum API token does not authenticate the
browser console.

## Canonical data sources

All state is read from existing services — nothing is recomputed
(UIX6-R001/R002):

- Tenant health: `SupportTenantHealthService`, `TenantRuntimeProbeService`.
- Diagnostic timeline: `SupportDiagnosticTimelineService` (already redacted).
- Domain viewers: `SupportBillingViewerService`, `SupportOnboardingViewerService`,
  `SupportAndroidRuntimeViewerService`.
- Application/infra/queue/scheduler health + metrics: `ObservabilityHealthService`,
  `InfrastructureHealthCheckService`, `QueueHealthService`,
  `SchedulerHealthService`, `FailedJobDiagnosticsService`,
  `ObservabilityMetricsService`.
- Anomalies / alert suggestions (read only): `ObservabilityAnomalyScanService::detectAll`,
  `ObservabilityIncidentSuggestionService::list`.
- Incidents: `ProductionIncident` (platform), `TenantSupportIncident` (tenant).

## Health & freshness semantics

- The canonical health aggregate folds `no_runs_recorded` and missing subsystems
  into "healthy". The presentation layer restores truth: each component receives a
  `display_status` that becomes `unknown` when evidence is absent, and the
  persisted snapshot `checked_at` is surfaced as the freshness `as_of` with an
  explicit stale/missing banner (UIX6-R011/R012/R013).
- Alert state and incident state are kept semantically distinct; an incident
  being resolved does not assert that every metric is healthy, and an alert
  clearing does not assert incident resolution (UIX6-R014).

## Redaction & privacy

- No raw logs, stack traces, environment values, credentials, tokens, cookies,
  or private headers are rendered (UIX6-R009).
- Incident free text is passed through `SupportRedactor`; evidence references are
  exposed as presence-not-payload (UIX6-R009/R019).
- Owner views expose only tenant-safe operational data: no affected-tenant list,
  no host/IP/worker/DB-role identifiers, no internal incident notes (UIX6-R010).
- Audit of privileged reads uses `AdminAuditLogger` sanitization (UIX6-R019).

## Read-only-first & mutation prerequisites

- UIX-6 ships read-only (UIX6-R015/R016). No incident/alert/tenant/device/sync/
  payment/settlement mutation route exists on the console.
- A future browser mutation requires authorization, confirmation, idempotency,
  audit, regression tests, and compensation/rollback semantics (UIX6-R017), and a
  governed canonical mutation service to delegate to.

## Cache isolation, polling & performance

- Any cache key must include surface, identity, tenant, query scope, and freshness
  window; tenant A data is never served to tenant B via cache reuse
  (UIX6-R020/R021).
- Lists and timelines are paginated or strictly bounded; per-page health is
  computed only for the page's tenants (UIX6-R022).
- Search/filter/sort fields are explicitly whitelisted (UIX6-R025).
- No real-time claims; no aggressive polling is introduced (UIX6-R023/R024).

## Authenticated evidence, HTTPS & deployment governance

- UIX-6 GO requires authenticated production runtime verification with safe
  throwaway Platform Admin and Tenant Owner accounts and synthetic, non-sensitive
  records; CI-only browser behavior is insufficient (UIX6-R029).
- HTTPS `aishpos.online` and the HTTP→HTTPS redirect must remain active
  (UIX6-R030).
- Production Artisan cache operations preserve PHP-FPM ownership of
  `storage/framework` and `bootstrap/cache` (UIX6-R031).
- Every deploy is bracketed by a DaengtisiaMS non-regression check; DMS must be
  unchanged (UIX6-R032).

## Tests (release blockers)

Cross-tenant support/incident isolation, cache isolation, raw-log/stack-trace/
secret/PII leakage, truthful health-freshness, surface separation, and auth
allow/deny are all mandatory release blockers (UIX6-R027/R028), exercised by the
`Uix6*` feature tests and enforced by `scripts/uix6_design_gate.sh` +
`scripts/verify_application_foundation_rules.sh` in `.github/workflows/uix6-ci.yml`.
