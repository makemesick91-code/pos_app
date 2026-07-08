# Sprint 28 — Usage Ledger Anomaly Detection & Governed Repair Foundation

## Summary

Sprint 28 adds a **read-only anomaly detector** for the Sprint 27 append-only
`tenant_usage_events` ledger and a **governed, CLI-only repair** workflow that
corrects effective usage **without ever mutating or deleting an original ledger
event**. Detection is server-side and redacted; repair is dry-run by default,
requires explicit `--apply`/`--reason`/`--actor`, is audit-logged, idempotent, and
can never drive effective usage negative.

Runtime rules `ULR-R001..R016` are locked in
`backend/config/usage_ledger_anomaly.php` and `docs/PROJECT_RULES.md`.

## Anomaly detector

`App\Services\UsageLedgerAnomaly\UsageLedgerAnomalyDetector` (+ `...Repository`,
`...Anomaly`, `...AnomalySeverity`, `...AnomalySummary`) reads the ledger and emits
redacted anomalies:

- `duplicate_idempotency` (critical, **auto-repairable**) — double-count drift.
- `unsanitized_metadata` (critical) — secret-looking metadata key; **key name only**.
- `missing_required_field`, `invalid_quantity`, `invalid_period`, `unknown_meter`
  (warning) — reported for manual review.

Detection is read-only (`ULR-R001`, `ULR-R002`), scoped by tenant/meter/severity,
and never prints secret values (`ULR-R006`).

## Governed repair workflow

Because ledger `quantity` is `unsignedInteger`, corrections are stored as signed
`quantity_delta` in a new governed repair record table
`tenant_usage_ledger_repairs` (append-only for the ledger is preserved). Effective
usage = `max(0, ledger_count + Σ repair_delta)` — implemented in
`UsageEventLedgerService::meterCount()` / `rawMeterCount()` / `repairDelta()` and
consumed by `TenantUsageMeter` → `TenantUsageLimitService` enforcement.

- `UsageLedgerRepairPlanner` — dry-run plan (auto vs manual-review).
- `UsageLedgerRepairService` — the only writer; clamps, dedupes by `repair_key`,
  audit-logs.
- `UsageLedgerRepairAuditPayload` — redacted audit metadata builder.
- `UsageLedgerRepairSummaryService` — read-only, redacted repair history.

### Dry-run / apply safety

`usage-ledger:repair-apply` refuses to run without `--apply` or `--dry-run`,
always requires `--reason` and `--actor`, only writes `apply` decisions, skips
`manual_review`, is idempotent, and clamps effective usage ≥ 0 (`ULR-R007`,
`ULR-R008`, `ULR-R010`, `ULR-R011`, `ULR-R013`).

### Audit & redaction

Each repair record carries reason/applied_by/applied_at + redacted metadata and is
itself a governed audit artifact; when a platform-admin user exists, apply also
writes an `admin_audit_logs` entry (`ACTION_USAGE_LEDGER_REPAIR_APPLIED`).

## Admin read-only visibility

Behind `platform.admin`, read-only:

- `GET /api/v1/admin/usage-ledger/anomalies`
- `GET /api/v1/admin/tenants/{tenant}/usage-ledger/anomalies`
- `GET /api/v1/admin/usage-ledger/repair-summary`

There is **no** repair-apply route and **no** ledger update/delete route in
runtime (`ULR-R009`); repair is CLI-only. Non-admins are blocked; output is
redacted (`ULR-R012`, `ULR-R006`).

## Commands added

- `usage-ledger:anomaly-scan` — read-only scan; exit non-zero on critical unless `--allow-critical`.
- `usage-ledger:repair-plan` — dry-run plan.
- `usage-ledger:repair-apply` — governed apply (`--apply`/`--dry-run` + `--reason` + `--actor`).
- `usage-ledger:repair-summary` — redacted repair history.
- `usage-ledger:go-no-go` — Sprint 28 release gate.

## Rules added

`ULR-R001..R016` in `backend/config/usage_ledger_anomaly.php`, mirrored in
`docs/PROJECT_RULES.md`, plus `sprint_28` foundation flags in
`backend/config/pos_foundation.php`. CI greps `ULR-R007`, `ULR-R010`, `ULR-R016`.

## Safe cleanup

Fixed the latent `TenantUsageMeter::isMeterable()` config-dot-path bug (dotted
usage-limit keys like `reports.exports.monthly` were wrongly resolved as nested
config paths and always returned false). Now looks the key up literally, with a
regression test. Enforcement was never affected (allow/deny used the `match` in
`currentUsage()`), but the reported `meterable` flag is now correct.

## Regression guarantees

- Sprint 27: `reports.exports.monthly` stays `meterable: true`; one event per
  successful export; retries don't double count; blocked/failed exports record
  nothing (`ULR-R014`).
- Sprint 26: usage-limit enforcement unchanged (now reads effective usage);
  unlimited unblocked, numeric limits still block at cap.
- Sprint 25: lifecycle enforcement runs first; suspended stays blocked.
- Sprint 24 renewal/dunning untouched.

## Tests / gate evidence

- `UsageLedgerAnomalyDetectorTest`, `UsageLedgerRepairTest`,
  `UsageLedgerAdminApiTest`, `UsageLedgerRulesLockTest`,
  `UsageLedgerCommandsTest`, plus Sprint 27 regression assertions.
- `usage-ledger:go-no-go` green; `report-export-metering:go-no-go`,
  `tenant-plan:go-no-go`, `tenant-lifecycle:go-no-go` green.
- `scripts/sprint28_smoke.sh` and `.github/workflows/sprint28-ci.yml`.

## Deferred / follow-up

- Only duplicate double-count drift is auto-repairable; missing-field / invalid
  quantity-period / unknown-meter / unsanitized-metadata anomalies remain
  manual-review-only until a dedicated, safe governance migration is designed.
- A sanitizer back-fill migration for legacy unsanitized metadata is out of scope.
- Repair currently corrects the `reports.exports.monthly` meter; future metered
  meters inherit the same effective-usage path automatically.
