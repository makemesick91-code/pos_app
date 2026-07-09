# Sprint 36 — Evidence: Observability, Health Monitoring, Queue & Production Diagnostics

Rules: `OBS-R001..OBS-R032` (see `backend/config/observability_governance.php`,
`backend/config/pos_foundation.php`, `docs/PROJECT_RULES.md`).

## What shipped

- Governance config `config/observability_governance.php` with OBS-R001..R032,
  config-driven thresholds, hard guardrails (all locked `false`), command +
  prior-gate + doc contracts.
- 4 additive tables: `observability_health_snapshots`,
  `observability_anomaly_events`, `observability_scheduler_runs`,
  `observability_alert_suggestions` (+ Eloquent models).
- 20 services under `App\Services\Observability` (health, infra, queue, failed
  jobs, queue action, scheduler, tenant probe, 5 anomaly detectors, scan
  orchestrator, incident suggestion, metrics, audit, redactor, governance audit,
  go/no-go).
- Public `HealthCheckController` (`/health/live`, `/health/ready`) + 4 admin
  controllers under `Api/V1/Admin`.
- 5 form requests (reason-code validated) + 10 API resources.
- 11 `observability:*` console commands.
- 5 backend test classes, `scripts/sprint36_smoke.sh`,
  `.github/workflows/sprint36-ci.yml`.

## Key confirmations

- **OBS-R001** — Public `/health/live` and `/health/ready` return only
  `{ status, timestamp }`; tests assert no tenant/secret/component leakage.
- **OBS-R002** — All `api/v1/admin/observability/*` routes are behind
  `platform.admin`; a tenant owner receives 403 for every route (test).
- **OBS-R003** — Read-only by default; only anomaly ack/resolve, alert
  dismiss/accept, and governed (disabled) retry mutate — reason-required + audited.
- **OBS-R004..R009** — Redactor + infra checks assert no credential/DSN, cache
  key/value, raw path, job payload, exception, or stack leaks.
- **OBS-R010** — Failed-job retry disabled by default; POST returns HTTP 409 with
  `retry_enabled=false` (test).
- **OBS-R011** — Scheduler health detects fresh/stale/stuck runs (tests).
- **OBS-R012** — Tenant probes reuse Sprint 35 health; suspended → `critical`;
  tenant-isolated (tests).
- **OBS-R013..R019** — Each anomaly detector sourced from the correct Sprint
  30–35 ledger; scan dry-run persists nothing; `--execute` persists observability
  events only; domain state (e.g. overdue invoice) is untouched (tests).
- **OBS-R018** — Suggestion generation creates suggestions only (no support
  incident auto-created); accept creates a Sprint 35 incident via
  `SupportIncidentService`, audited (tests).
- **OBS-R024..R027** — No diagnostic marks an invoice paid, unlocks entitlement,
  reactivates a tenant/device, or bypasses manual suspension; guardrails locked
  false and asserted.
- **OBS-R028** — `admin_audit_logs` receives `OBSERVABILITY_*` entries for every
  mutation (tests).
- **OBS-R029** — Thresholds are config-driven (governance audit signal + test).
- **OBS-R030** — Prior Sprint 24–35 gates run green in smoke + CI.
- **OBS-R032** — `observability:go-no-go --strict` returns GO.

## Regression confirmations (Sprint 24–35)

Smoke + CI run every prior gate: `subscription-renewal`, `tenant-lifecycle`,
`tenant-plan`, `report-export-metering`, `usage-ledger`, `export-governance`,
`billing`, `payment-gateway`, `entitlement`, `onboarding`, `android-runtime`,
`support-ops` go/no-go — all green. The 4 `observability_*` tables are additive
and read prior ledgers without altering them.

## Security / PII / secret redaction confirmation

- No secret/PII in config, audit metadata, command output, smoke output, docs,
  API responses, health endpoints, metrics payloads, or tests.
- CI greps command output for `password|secret|server_key|sk_live_|private_key`
  and fails on any match.

## Commands (evidence)

```
php artisan observability:governance-audit          # all PASS
php artisan observability:go-no-go --strict --json   # Decision: GO
php artisan observability:health --json
php artisan observability:anomaly-scan --json        # DRY-RUN, persists nothing
php artisan observability:anomaly-scan --execute --json
bash scripts/sprint36_smoke.sh                       # PASS=<n> FAIL=0
```

## Rollback steps

1. Revert the Sprint 36 merge commit.
2. If deployed, run the four `observability_*` `down()` migrations (additive drop).
3. No prior-sprint code path references Sprint 36; nothing else changes.

## Deferred items / risks

- Governed job retry disabled by default (enable requires idempotent allow-list).
- Queue depth accuracy depends on the deployed driver (empty on CI `sync`).
- Anomaly thresholds ship conservative; production tuning is config-only.
- No external monitoring vendor integration (intentionally vendor-neutral).
