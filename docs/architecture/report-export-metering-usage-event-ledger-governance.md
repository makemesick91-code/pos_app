# Report Export Metering & Usage Event Ledger Governance (Sprint 27)

Status: **Implemented**. This foundation closes the Sprint 26 deferred
`reports.exports.monthly` meter with real runtime metering backed by a server-side,
append-only **usage event ledger**.

Canonical rules: `UEL-R001..UEL-R015` (locked in
[`backend/config/usage_event_ledger.php`](../../backend/config/usage_event_ledger.php),
mirrored in [`docs/PROJECT_RULES.md`](../PROJECT_RULES.md)).

## Why

Sprint 26 shipped `reports.exports.monthly` as a *declared but deferred* usage
limit (`meterable: false`) because no report export event was persisted. Without a
recorded event, the limit could never actually block an export. Sprint 27 adds the
ledger that makes the meter real, and wires the enforcement.

## Server-side source of truth

`tenant_usage_events` is the append-only source of truth for tenant usage events
(`UEL-R001`, `UEL-R002`). Every event carries: `tenant_id`, `event_key`,
`event_category`, `meter_key`, `quantity`, `occurred_at` (server clock),
`period_key`, `idempotency_key`, `source`, optional actor/subject/fingerprint, and
redacted `metadata`.

Writes go through exactly one writer — `UsageEventRecorder` (via
`UsageEventLedgerService::append`). Normal runtime never updates or deletes an
event; there is no runtime mutation route.

```
tenant → lifecycle (Sprint 25) → plan assignment (Sprint 26)
      → plan entitlement reports.basic  → plan usage limit reports.exports.monthly
report export route ──guards──> controller ──on success──> UsageEventLedgerService.append
      usage event ledger (tenant_usage_events) ──count(period)──> TenantUsageMeter
      TenantUsageMeter → TenantUsageLimitService → tenant.usage.limit guard
platform admin (read-only) → usage-events / summaries        Android → UX only
```

## Report export metering

- Meter key: `reports.exports.monthly` (`UEL-R006`), now `meterable: true`.
- Event key: `report.exported`; category: `report_export`; source: `api`.
- `ReportExportMeteringService::recordExport()` is called by the export controller
  **only after** the export has succeeded (summary computed, CSV built), so a
  failed export throws before recording and never counts (`UEL-R008`).
- `TenantUsageMeter::currentUsage('reports.exports.monthly')` reads
  `ReportExportMeteringService::currentMonthlyUsage()`, which counts the ledger for
  the current month period — never a stored counter (`UEL-R005`, `UEL-R006`).

### Idempotency strategy (`UEL-R004`, `UEL-R007`)

Recording is deduplicated by a **per-tenant unique** `idempotency_key`:

1. If the request carries an `Idempotency-Key` header, it is namespaced by tenant
   (`hdr:<tenant>:<sha256 header>`).
2. Otherwise a deterministic fingerprint is derived from
   `tenant + user + report type + normalized (sanitized) filters` plus a
   **per-minute time bucket** (`fp:<sha>:<YYYYMMDDHHmm>`). An accidental retry of
   the *same* export within the same minute collapses to one event; genuinely
   distinct exports (different filters) each count.

A duplicate is detected before insert and returns a `UsageEventDecision` with
`recorded=false, duplicate=true` — no new usage is counted.

### Period key strategy (`UEL-R005`)

`UsageEventPeriodResolver` maps a monthly meter to a stable `Y-m` key (e.g.
`2026-07`) from the server clock. Lifetime meters use the constant `lifetime`.

### Metadata redaction (`UEL-R003`)

`SanitizesUsageEventMetadata` strips `key: value` secret patterns from strings and
redacts secret-looking keys (password, token, secret, gateway keys, card/CVV, PIN,
OTP, NIK/SSN, …). Report export metadata holds only non-sensitive context: report
type, format, route name, and a sanitized filter summary. No full payloads.

## Runtime enforcement order

The export route `GET /api/v1/reports/daily-sales/export.csv` inherits, in order:

1. `tenant.lifecycle` (operational group, Sprint 25) → suspended ⇒ `423 TENANT_SUSPENDED`.
2. `tenant.entitled:reports.basic` (reports group, Sprint 26) → unentitled ⇒ `403 FEATURE_NOT_ENTITLED`.
3. `tenant.usage.limit:reports.exports.monthly` (Sprint 27) → over quota ⇒ `429 USAGE_LIMIT_EXCEEDED`.
4. `DailySalesCsvExportController` → on success appends exactly one ledger event.

`ReportExportMeteringEnforcementAuditService` verifies this chain exists and is in
order (`UEL-R009`, `UEL-R010`, `UEL-R011`).

## Platform admin governance (`UEL-R013`)

Read-only, `platform.admin` only, counts/redacted only:

- `GET /api/v1/admin/tenants/{tenant}/usage-events` (paginated, tenant-scoped)
- `GET /api/v1/admin/tenants/{tenant}/usage-events/summary`
- `GET /api/v1/admin/usage-event-ledger/summary` (cross-tenant counts only)
- `GET /api/v1/admin/report-export-metering/summary`

There is no update/delete ledger route in normal runtime.

## Interaction with prior sprints

- Sprint 25 tenant lifecycle stays first; a suspended tenant is blocked even with
  quota remaining.
- Sprint 26 plan/entitlement/usage services are reused unchanged; `products.max`
  and `transactions.monthly` metering are unaffected.
- Sprint 24 renewal/dunning automation is untouched.

## Android

Android may present a report export limit message (it already maps
`USAGE_LIMIT_EXCEEDED`), but server-side enforcement is authoritative (`UEL-R012`).

## Commands & gate

- `usage-event-ledger:readiness`
- `usage-event-ledger:summary`
- `report-export-metering:summary`
- `report-export-metering:enforcement-audit`
- `report-export-metering:go-no-go` (Sprint 27 GO gate, `UEL-R014`)

CI: `.github/workflows/sprint27-ci.yml`; smoke: `scripts/sprint27_smoke.sh`.
