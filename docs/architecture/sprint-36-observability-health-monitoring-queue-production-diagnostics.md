# Sprint 36 — Observability, Health Monitoring, Queue & Production Diagnostics

## Scope

Make the POS Android SaaS **production-observable** so operational issues are
detected before tenants complain — without ever mutating tenant/billing/
entitlement/onboarding/device state, exposing secrets/PII, or bypassing a prior
sprint gate. Sprint 36 is additive and does not change Sprint 23–35 semantics.

Delivered:

- Public, minimal liveness/readiness endpoints (`/health/live`, `/health/ready`).
- Platform-admin observability console (`api/v1/admin/observability/*`):
  application health, database/cache/storage/config diagnostics, queue + failed
  job diagnostics, scheduler health, tenant runtime probes.
- Read-only anomaly detection over the Sprint 30–35 ledgers: Android sync,
  billing/payment webhook, entitlement, onboarding, export/report.
- Safe operational dashboard metrics.
- A vendor-neutral alert/incident suggestion foundation that integrates with the
  Sprint 35 support incident service without auto-mutating tenant state.
- A governance audit + hard GO/WATCH/NO-GO gate.

## Non-goals

- No external monitoring vendor (Datadog/NewRelic/Sentry/etc.) dependency.
- No external network or payment-gateway dependency in CI.
- No queue retry/replay enabled by default (governed + disabled).
- No React/Vue/heavy frontend. No Android observability UI.
- No VPS deploy.
- No mutation of any domain state from a diagnostic path.

## Commercial SaaS chain

```
Plan → Invoice → Payment Intent → Gateway Settlement → Collection →
Entitlement Runtime Access → Tenant Onboarding → Android Runtime →
Support Operations → Observability
```

Observability is the read-only terminal layer: every detector reads a prior-sprint
ledger and never writes back to it.

## Observability architecture

| Concern | Service | Source of truth |
| --- | --- | --- |
| App health | `ObservabilityHealthService` | infra + queue + scheduler |
| Infra diagnostics | `InfrastructureHealthCheckService` | DB/cache/storage/config |
| Queue health | `QueueHealthService` | `jobs` / `failed_jobs` |
| Failed job diagnostics | `FailedJobDiagnosticsService` | `failed_jobs` |
| Governed retry | `QueueActionService` | config (disabled by default) |
| Scheduler health | `SchedulerHealthService` | `observability_scheduler_runs` |
| Tenant runtime probe | `TenantRuntimeProbeService` | Sprint 35 `SupportTenantHealthService` |
| Sync anomalies | `AndroidSyncAnomalyService` | Sprint 34 sync ledgers |
| Billing/payment anomalies | `BillingPaymentAnomalyService` | Sprint 30/31 state |
| Entitlement anomalies | `EntitlementAnomalyService` | Sprint 32 decisions |
| Onboarding anomalies | `OnboardingAnomalyService` | Sprint 33 runs |
| Export/report anomalies | `ExportReportAnomalyService` | Sprint 27–29 decisions |
| Scan orchestration | `ObservabilityAnomalyScanService` | detectors → anomaly events |
| Metrics | `ObservabilityMetricsService` | aggregates |
| Suggestions | `ObservabilityIncidentSuggestionService` | anomaly events → Sprint 35 |
| Audit | `ObservabilityAuditService` | `admin_audit_logs` |
| Redaction | `ObservabilityRedactor` | — |
| Governance audit | `ObservabilityGovernanceAuditService` | config |
| Go/no-go | `ObservabilityGoNoGoService` | all of the above |

## Public health endpoint safety model (OBS-R001)

`/health/live` returns `{ status: "ok", timestamp }`. `/health/ready` returns
`{ status: "ok"|"degraded", timestamp }` (HTTP 200/503). Neither exposes tenant
data, environment secrets, DB credentials, component internals, or PII. Both can
be disabled per env. The guardrail
`observability_public_endpoint_exposes_tenant_or_secret_allowed=false` locks this.

## Admin observability route model (OBS-R002/R003/R023)

Everything under `api/v1/admin/observability/*` is behind `platform.admin`, is
read-only by default, and returns redacted aggregate-safe data. The only mutating
routes are anomaly acknowledge/resolve, alert-suggestion dismiss/accept, and a
governed (default-disabled) failed-job retry; each requires an enumerable
`reason_code` and is audited to `admin_audit_logs`.

## Infrastructure health model (OBS-R005/R006/R007)

- Database: `select 1` round-trip; reports connection NAME + driver — never a DSN
  or password.
- Cache: put/get/forget a probe key; reports store NAME — never a key or value.
- Storage: put/get/delete a probe file under a safe non-tenant dir; reports disk
  NAME — never an absolute path.
- Config: reports booleans only (app key set, debug safe for env) — never a value.

## Queue / failed job diagnostics model (OBS-R008/R009/R010)

`QueueHealthService` derives a status from pending backlog, oldest-pending age and
failed-job count via config thresholds. `FailedJobDiagnosticsService` groups
`failed_jobs` by a redacted job label + count; it never returns the raw payload,
exception message, or stack trace. `QueueActionService` retry is disabled by
default and returns a governed 409 "not supported"; when ever enabled it is
reason-required, audited, and idempotency-safe only.

## Scheduler health model (OBS-R011)

`observability_scheduler_runs` records a heartbeat per command
(`recordStart`/`recordComplete`). `SchedulerHealthService` flags stale (last
completion older than `scheduler_stale_seconds`), stuck (still "started" past the
window), failed, and long-running commands. With no runs recorded it is healthy
(CI-safe).

## Tenant runtime probe model (OBS-R012)

`TenantRuntimeProbeService` reuses the Sprint 35 `SupportTenantHealthService` for
the canonical, deterministic health computation (plan/billing/payment/
entitlement/onboarding/device-sync/incident). Manual suspension always wins
(`critical`). Probes are tenant-isolated; nothing lifts a suspension.

## Anomaly models

- **Android sync (OBS-R013):** repeated failed/rejected batches, high conflict
  rate, duplicate replay spikes, revoked-device sync attempts.
- **Billing/payment (OBS-R014/R015):** overdue-past-grace invoices, repeated
  failed/cancelled payments, invalid-signature (rejected) webhook spikes
  (app-level — gateway events carry no tenant id), stuck-pending payment intents.
- **Entitlement (OBS-R016):** denial-rate spikes from the decision ledger.
- **Onboarding (OBS-R017):** failed and stuck provisioning runs.
- **Export/report (OBS-R019):** repeated export/report denials from the
  entitlement ledger (preserves Sprint 27–29 metering/governance).

A scan is dry-run by default; `--execute` upserts `observability_anomaly_events`
only — dedup by `(tenant_id, anomaly_key)` increments `occurrence_count` and
`last_seen_at`. No domain state is mutated.

## Alert / incident suggestion model + Sprint 35 integration (OBS-R018)

`ObservabilityIncidentSuggestionService.generateFromAnomalies()` creates
SUGGESTIONS only from open anomalies at/above `min_severity_for_suggestion`; it
never auto-creates a support incident and never mutates tenant state. Accepting a
tenant-scoped suggestion creates a Sprint 35 support incident **only** through
`SupportIncidentService` (audited, redacted) and links it via
`support_incident_id`. Dismiss is audited.

## Redaction model (OBS-R004)

`ObservabilityRedactor` mirrors the Sprint 30–35 redactors: recursively redacts
secret/PII-looking keys (password, token, secret, signature, email, phone, name,
address, NIK, card, raw payload, DSN, path, exception/stack) and caps
string/depth. All persisted metadata and all API/command output flow through it.

## Audit model (OBS-R028)

`ObservabilityAuditService` writes an `OBSERVABILITY_*` entry to the shared
`admin_audit_logs` for every mutation (anomaly acknowledge/resolve, alert
dismiss/accept, denied/failed job-retry) with redacted metadata. Reads are never
audited.

## Route matrix

| Method | Path | Auth | Mutates |
| --- | --- | --- | --- |
| GET | /health/live | public | no |
| GET | /health/ready | public | no |
| GET | /api/v1/admin/observability/health | platform.admin | no |
| GET | /api/v1/admin/observability/health/infrastructure | platform.admin | no |
| GET | /api/v1/admin/observability/health/tenants | platform.admin | no |
| GET | /api/v1/admin/observability/health/tenants/{tenant} | platform.admin | no |
| GET | /api/v1/admin/observability/queues | platform.admin | no |
| GET | /api/v1/admin/observability/failed-jobs | platform.admin | no |
| POST | /api/v1/admin/observability/failed-jobs/{job}/retry | platform.admin | governed (disabled) |
| GET | /api/v1/admin/observability/scheduler | platform.admin | no |
| GET | /api/v1/admin/observability/anomalies | platform.admin | no |
| GET | /api/v1/admin/observability/anomalies/{anomaly} | platform.admin | no |
| POST | /api/v1/admin/observability/anomalies/{anomaly}/acknowledge | platform.admin | observability only |
| POST | /api/v1/admin/observability/anomalies/{anomaly}/resolve | platform.admin | observability only |
| GET | /api/v1/admin/observability/metrics | platform.admin | no |
| GET | /api/v1/admin/observability/alerts/suggestions | platform.admin | no |
| POST | /api/v1/admin/observability/alerts/suggestions/{suggestion}/dismiss | platform.admin | observability only |
| POST | /api/v1/admin/observability/alerts/suggestions/{suggestion}/accept | platform.admin | creates Sprint 35 incident (governed) |
| GET | /api/v1/admin/observability/governance | platform.admin | no |

## Command matrix

`observability:health`, `observability:infra-check`, `observability:queue-health`,
`observability:failed-jobs`, `observability:scheduler-health`,
`observability:tenant-probe`, `observability:anomaly-scan` (dry-run default,
`--execute`), `observability:metrics-summary`, `observability:alert-suggestions`
(`--generate`), `observability:governance-audit`, `observability:go-no-go`.

## Data model

- `observability_health_snapshots` — aggregate, redacted health snapshots.
- `observability_anomaly_events` — detected anomalies (dedup by tenant + key).
- `observability_scheduler_runs` — scheduler/command heartbeats.
- `observability_alert_suggestions` — vendor-neutral alert/incident suggestions.

## Dependency graph

Observability depends on (reads only) Sprint 27–35 ledgers and the Sprint 35
`SupportTenantHealthService`/`SupportIncidentService`. Nothing in Sprint 27–35
depends on Sprint 36.

## Rollback

Revert the Sprint 36 branch/merge. The four `observability_*` tables are additive;
dropping them (their `down()` migrations) removes all Sprint 36 state without
touching any prior-sprint table. No prior-sprint code path calls Sprint 36.

## Tests / CI / smoke evidence

- Backend tests: `Sprint36Observability{Governance,Services,Anomaly,Api,Commands}Test`.
- Smoke: `scripts/sprint36_smoke.sh`.
- CI: `.github/workflows/sprint36-ci.yml` (backend tests, governance audit,
  go/no-go, prior Sprint 24–35 gates, smoke, OBS rule grep, Android build/unit).
- Gate: `observability:governance-audit` + `observability:go-no-go --strict`.

## Deferred risks

- Real queue-depth accuracy depends on the deployed queue driver; on `sync`/CI the
  tables are empty and health is healthy by construction.
- Governed job retry is intentionally disabled; enabling it requires populating
  `job_retry.idempotent_job_allowlist` and is out of scope here.
- Anomaly thresholds ship with conservative defaults; production tuning is a
  config change (OBS-R029).
