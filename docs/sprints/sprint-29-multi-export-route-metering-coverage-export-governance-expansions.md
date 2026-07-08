# Sprint 29 — Multi-Export Route Metering Coverage & Export Governance Expansions

## Scope

Expand Sprint 27 report-export metering (which only metered the daily-sales CSV
export) into a **governed coverage surface**: discover every export-like route
server-side, register each as metered or explicitly exempt, audit that metered
routes are guarded/idempotent/sanitized, and gate GO on `export-governance:go-no-go`.
Preserve Sprint 28 anomaly/repair, Sprint 27 metering, Sprint 26 usage limits,
Sprint 25 lifecycle, and Sprint 24 renewal/dunning. No breaking change.

Foundation only; runtime coverage is real (route discovery + audit + gate +
admin visibility), not docs-only.

## Runtime changes

- **`config/export_governance.php`** — canonical export route registry (metered +
  exempt), server-side discovery config, `EGC-R001..R015` rules, guardrail flags,
  command/prior-gate/doc contracts.
- **`App\Services\ExportGovernance\`**
  - `ExportRouteRegistry` — read model over the registry (metered/exempt/keys).
  - `ExportRouteDiscoveryService` — server-side export-like route scan +
    registered/unregistered classification.
  - `ExportGovernanceAuditService` — enforcement gaps → GO/WATCH/NO_GO.
  - `ExportGovernanceCoverageService` — redacted coverage summary.
  - `ExportGovernanceGoNoGoService` — aggregate gate.
- **Commands** — `export-governance:route-scan`, `:coverage-summary`,
  `:metering-audit`, `:go-no-go`.
- **Admin (read-only, `platform.admin`)** — `GET /api/v1/admin/export-governance/routes`,
  `/coverage-summary`, `/metering-summary` via `AdminExportGovernanceController`.
- **`config/pos_foundation.php`** — Sprint 29 rules flags + `sprint_29`.
- **`docs/PROJECT_RULES.md`** — Sprint 29 runtime rule + `EGC-R001..R015`.

## Export route discovery result

Server-side scan (`ExportRouteDiscoveryService`) over 311 routes discovers exactly
**one** true file-export route; it is registered and metered. Current export-surface
coverage is **100%**.

```
Export-like route scan (1 discovered):
 [registered:metered] GET api/v1/reports/daily-sales/export.csv
Unregistered export-like routes: 0
```

## Export routes — metered

| Route | Report type | Format | Entitlement | Meter | Event |
|-------|-------------|--------|-------------|-------|-------|
| `GET api/v1/reports/daily-sales/export.csv` | daily-sales | csv | `reports.basic` | `reports.exports.monthly` | `report.exported` |

Middleware order (audited): `tenant.lifecycle` → `tenant.entitled:reports.basic`
→ `tenant.usage.limit:reports.exports.monthly`.

Idempotency: explicit `Idempotency-Key` header, else deterministic
tenant+route+user+report+filter+minute fingerprint (retry-safe, EGC-R008).

## Export routes — exempt (with reason)

| Route | Format | Reason |
|-------|--------|--------|
| `GET api/v1/sales/{sale}/receipt` | json | Per-sale POS receipt view returns backend-authoritative JSON for a single sale; operational POS action, not a downloadable monthly report export. |
| `GET api/v1/reports/daily-sales` | json | Read-only daily-sales JSON report view; no downloadable export artifact. |
| `GET api/v1/reports/payment-summary` | json | Read-only payment-summary JSON report view; no downloadable export artifact. |
| `GET api/v1/reports/inventory-movements-summary` | json | Read-only inventory-movements-summary JSON report view; no downloadable export artifact. |

These are explicit, documented declarations so a future scan can never silently
treat them as unmetered exports (EGC-R010).

## Middleware / enforcement order

Metered exports enforce, in order: lifecycle (423 `TENANT_SUSPENDED`) →
entitlement (403 `FEATURE_NOT_ENTITLED`) → usage limit (429
`USAGE_LIMIT_EXCEEDED`). Under quota, the export succeeds and the ledger
increments by exactly one.

## Idempotency & metadata redaction

Reused from Sprint 27 `ReportExportMeteringService`: per-tenant unique idempotency
key; sanitized metadata via `SanitizesUsageEventMetadata` (no secrets/tokens/PII).
The audit FAILS a metered route that declares no idempotency strategy or an
inactive sanitizer.

## Usage ledger / anomaly interaction

No new ledger write path and no ledger mutation route. Metering still appends
`report.exported` events via `UsageEventLedgerService`; anomaly detection stays
read-only and governed repair stays CLI-only. `usage-ledger:go-no-go --strict`
stays GO; `reports.exports.monthly` stays `meterable: true`.

## Platform admin export governance visibility

`GET /api/v1/admin/export-governance/{routes,coverage-summary,metering-summary}` —
`platform.admin` only, read-only, redacted route governance (not tenant usage),
no metering-bypass/ledger-mutation route (EGC-R011/R012).

## Rules added

`EGC-R001..R015` in `config/export_governance.php`, mirrored in
`docs/PROJECT_RULES.md`, locked by `ExportGovernanceRulesLockTest` and CI grep for
`EGC-R003`, `EGC-R008`, `EGC-R015`.

## Commands added

- `export-governance:route-scan`
- `export-governance:coverage-summary`
- `export-governance:metering-audit`
- `export-governance:go-no-go` ← Sprint 29 release gate

## Tests / gate evidence

- `ExportGovernanceRegistryTest` — registry lists metered route, exempt requires
  reason, discovery finds export-like routes, unregistered detected.
- `ExportGovernanceRuntimeTest` — successful export records one event, retry
  idempotent, lifecycle/entitlement/usage blocking, metadata redacted, canonical
  meter/category.
- `ExportGovernanceAdminApiTest` — platform admin can view coverage; non-admin
  cannot; no bypass/mutation route.
- `ExportGovernanceCommandsTest` — route-scan/coverage-summary/metering-audit/
  go-no-go behavior + prior gates green.
- `ExportGovernanceRulesLockTest` — `EGC-R001..R015`, guardrails, meterable,
  pos_foundation + PROJECT_RULES lock.

## CI evidence

`.github/workflows/sprint29-ci.yml` — foundation + smoke, backend tests
(export-governance gate + Sprint 0–28 regression), export governance gate
(scan/coverage/audit/go-no-go + Sprint 25–28 prior gates), rules grep, Android
build & unit tests.

## Deferred / follow-up

- No XLSX/PDF export routes exist yet; when added they must be registered here and
  will be caught by `export-governance:metering-audit` if unregistered.
- Per-format sub-meters (e.g. `reports.exports.pdf.monthly`) are out of scope;
  Sprint 29 keeps the single canonical `reports.exports.monthly` meter.

## GO checklist

- [x] Every export-like route registered or explicitly exempted (100% coverage).
- [x] Metered export routes carry lifecycle → entitlement → usage guards in order.
- [x] Successful export increments ledger exactly once; retry idempotent.
- [x] Blocked/failed export does not consume quota.
- [x] Metadata redacted; no bypass/ledger-mutation route.
- [x] `reports.exports.monthly` remains `meterable: true`.
- [x] `export-governance:go-no-go` green; Sprint 25/26/27/28 gates green.
- [x] `EGC-R001..R015` locked in config + docs + tests + CI grep.
