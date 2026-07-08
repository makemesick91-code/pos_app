# Sprint 27 — Report Export Metering & Usage Event Ledger Governance Foundation

Status: **Implemented**. Closes the Sprint 26 deferred `reports.exports.monthly`
meter with a real, server-side, append-only usage event ledger and runtime report
export metering.

## Scope

1. `tenant_usage_events` — append-only usage event ledger (source of truth).
2. Report export metering: a successful report export records exactly one
   `report.exported` usage event; the export limit is enforced from the ledger.
3. `reports.exports.monthly` flips from `meterable: false` → `meterable: true`.
4. Server-side enforcement only; Android is UX. Tenant lifecycle (Sprint 25) stays
   first; plan/entitlement/usage (Sprint 26) reused as guards.
5. Platform admin read-only usage event inspection + summaries.
6. `UEL-R001..UEL-R015` governance rules, CLI gate, tests, CI, smoke, docs.

## Runtime changes

- Route `GET /api/v1/reports/daily-sales/export.csv` now carries
  `tenant.usage.limit:reports.exports.monthly` (after lifecycle + entitlement).
- `DailySalesCsvExportController` records a `report.exported` ledger event after a
  successful export (idempotent; failed/blocked exports never reach it).
- `TenantUsageMeter` now derives `reports.exports.monthly` current usage from the
  ledger. `products.max` / `transactions.monthly` metering unchanged.

## Migrations / schema

`tenant_usage_events`: `id, tenant_id, event_key, event_category, meter_key,
quantity, occurred_at, period_key, idempotency_key, source, actor_type, actor_id,
subject_type, subject_id, request_fingerprint, metadata (json, redacted), timestamps`.
Indexes: `(tenant_id, meter_key, period_key)`, `(tenant_id, event_key, occurred_at)`,
unique `(tenant_id, idempotency_key)`, `(source, created_at)`.

## Design

- **Usage event ledger**: append-only; single writer `UsageEventRecorder`;
  tenant-scoped reads (`UsageEventLedgerService`).
- **Idempotency**: per-tenant unique `idempotency_key` — `Idempotency-Key` header
  (namespaced) or deterministic `tenant+user+report+filters+minute` fingerprint.
- **Period key**: `UsageEventPeriodResolver` → `Y-m` for monthly meters.
- **Metadata redaction**: `SanitizesUsageEventMetadata` (secret/PII stripping).
- **Enforcement order**: lifecycle → entitlement → usage → action → append.

See [architecture doc](../architecture/report-export-metering-usage-event-ledger-governance.md).

## Enforcement behavior

| Tenant state | Response |
| --- | --- |
| Suspended (any quota) | `423 TENANT_SUSPENDED` |
| Active, no `reports.basic` entitlement | `403 FEATURE_NOT_ENTITLED` |
| Active, entitled, quota exhausted | `429 USAGE_LIMIT_EXCEEDED` |
| Active, entitled, quota available | export succeeds, ledger +1 |
| Retry with same idempotency key | export succeeds, ledger unchanged |

## Platform admin (read-only, redacted, `platform.admin`)

- `GET /api/v1/admin/tenants/{tenant}/usage-events`
- `GET /api/v1/admin/tenants/{tenant}/usage-events/summary`
- `GET /api/v1/admin/usage-event-ledger/summary`
- `GET /api/v1/admin/report-export-metering/summary`

No runtime update/delete ledger route (`UEL-R002`, `UEL-R013`).

## Rules added

`UEL-R001..UEL-R015` in `backend/config/usage_event_ledger.php`, mirrored in
`docs/PROJECT_RULES.md`, plus `pos_foundation` sprint_27 flags. CI greps
`UEL-R004`, `UEL-R008`, `UEL-R015`.

## Commands

`usage-event-ledger:readiness`, `usage-event-ledger:summary`,
`report-export-metering:summary`, `report-export-metering:enforcement-audit`,
`report-export-metering:go-no-go`.

## Tests

- `UsageEventLedgerTest` — record, sanitize, idempotency, period, summary, cross-tenant.
- `ReportExportMeteringTest` — success records one, retry no double, failed/blocked
  no record, meter from ledger, meterable true, limit blocks, unlimited allows,
  no entitlement 403, suspended 423, over quota 429.
- `UsageEventAdminApiTest` — admin view, non-admin blocked, redacted, no mutation route.
- `UsageEventLedgerCommandsTest` — the 5 commands + Sprint 26/25 go-no-go still pass.
- `UsageEventRulesLockTest` — `UEL-R001..R015` + pos_foundation sprint_27 locked.

## CI / smoke

- `.github/workflows/sprint27-ci.yml` — foundation + rules grep + smoke + backend
  tests (Sprint 0–27 regression) + report-export/tenant-plan/tenant-lifecycle
  go-no-go + Android build gate.
- `scripts/sprint27_smoke.sh` — structural + command smoke, `0 failures` on GO.

## Deferred / follow-up

- Only the daily-sales CSV export route is metered in Sprint 27 (the one existing
  export route). Additional export formats/reports register in
  `usage_event_ledger.report_export_guarded_routes` + `tenant_plan.usage_guarded_routes`
  as they are added.
- A governed admin repair/anomaly command for the ledger is intentionally left for
  a later sprint (Sprint 27 keeps the ledger append-only in runtime).

## Evidence

- Backend tests: see PR CI run.
- `report-export-metering:go-no-go`: GO.
- Prior GO tags unmoved: Sprint 24 `265ffde`, Sprint 25 `c931a4c`, Sprint 26 `9651be4`.
