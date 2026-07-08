# Multi-Export Route Metering Coverage & Export Governance (Sprint 29)

Server-side governance that guarantees **every export-like route** in the
application is registered, guarded, and (unless explicitly exempted) metered
against the tenant's `reports.exports.monthly` quota. Sprint 29 expands the
Sprint 27 report-export metering — which only metered the daily-sales CSV — into
a governed coverage surface with server-side route discovery, a canonical
registry, an enforcement audit, a GO/NO-GO gate, and read-only platform-admin
visibility. Android/UI is never the authority.

## Dependency graph

```
route table ──► ExportRouteDiscoveryService (server-side scan, EGC-R002)
                     │  export-like = URI ends .csv/.xlsx/.xls/.pdf
                     │               OR dot/slash segment ∈ {export,download}
                     ▼
              ExportRouteRegistry  ◄── config/export_governance.php (routes)
                     │                     ├─ metered  → guards + meter + event
                     │                     └─ exempt   → explicit reason (EGC-R010)
                     ▼
     ExportGovernanceAuditService (gaps: unregistered / unguarded / mis-ordered /
                     │             non-canonical key / no idempotency / no sanitizer /
                     │             exemption w/o reason / meter not meterable)
                     ▼
     ExportGovernanceGoNoGoService ──► export-governance:go-no-go (EGC-R014)
                     │  + Sprint 29 command contract
                     │  + Sprint 25/26/27/28 prior gates
                     │  + required docs + meterable check
                     ▼
     ExportGovernanceCoverageService ──► admin read-only endpoints (EGC-R011)
                                          coverage-summary command

metered export request:
  tenant.lifecycle ─► tenant.entitled:reports.basic ─► tenant.usage.limit:reports.exports.monthly
     (423 SUSPENDED)      (403 FEATURE_NOT_ENTITLED)       (429 USAGE_LIMIT_EXCEEDED)
                                                                     │ pass
                                                                     ▼
                                       controller builds export ─► ReportExportMeteringService
                                                                     │ success only (EGC-R006/R007)
                                                                     ▼
                                       tenant_usage_events +1 (report.exported / report_export)
                                       idempotent per-tenant key (EGC-R008); metadata sanitized (EGC-R009)
```

## Export route discovery (server-side, EGC-R002)

`ExportRouteDiscoveryService` walks Laravel's live route collection. A route is
**export-like** when:

- its URI ends with a configured export extension (`.csv`, `.xlsx`, `.xls`,
  `.pdf`), **or**
- any `/`- or `.`-delimited path segment is exactly an export/download token
  (`export`, `exports`, `download`, `downloads`).

Hyphenated segments are **not** split, so admin governance/summary endpoints such
as `report-export-metering/summary` and `export-governance/*` are never mistaken
for exports; a small `ignore_signatures` list keeps read-only export-governance
summaries off the scan as belt-and-suspenders.

### Current export surface

| Route | Disposition | Format | Meter |
|-------|-------------|--------|-------|
| `GET api/v1/reports/daily-sales/export.csv` | **metered** | csv | `reports.exports.monthly` |
| `GET api/v1/sales/{sale}/receipt` | exempt | json | — (per-sale POS receipt view) |
| `GET api/v1/reports/daily-sales` | exempt | json | — (report view, no download artifact) |
| `GET api/v1/reports/payment-summary` | exempt | json | — (report view, no download artifact) |
| `GET api/v1/reports/inventory-movements-summary` | exempt | json | — (report view, no download artifact) |

The scanner discovers exactly **one** true file-export route
(`reports/daily-sales/export.csv`), and it is metered — so current export-surface
coverage is **100%**. The four exempt entries are documented, reasoned
declarations (not scanner hits): they are report/receipt *views* that render
backend-authoritative JSON on screen and do not emit a downloadable export
artifact, so they intentionally do not consume the monthly export quota
(EGC-R010). Declaring them keeps a future scan from ever silently treating them
as unmetered exports.

## Registry (`config/export_governance.php`)

Keyed by `"METHOD uri"`. `metered` entries declare `entitlement`, `meter_key`,
`event_key`, `event_category`, `idempotency_strategy`, `metadata_sanitized`, and
the lifecycle/entitlement/usage requirements. `exempt` entries declare
`metering_enabled=false` and a mandatory `exempt_reason`.

## Enforcement audit (`ExportGovernanceAuditService`)

Fails (NO_GO) on any of:

- an export-like route discovered but not registered (EGC-R001);
- a metered route missing `tenant.lifecycle` → `tenant.entitled:<feature>` →
  `tenant.usage.limit:reports.exports.monthly`, or with those guards out of order
  (EGC-R003/R004);
- a metered route using a non-canonical meter or event key (EGC-R005/R006);
- a metered route with no `idempotency_strategy` or with the sanitizer inactive
  (EGC-R008/R009);
- an exemption with no reason, or an exemption that enables metering (EGC-R010);
- `reports.exports.monthly` not `meterable` (EGC-R013);
- any hard guardrail flag enabled.

## Runtime metering (reused from Sprint 27)

Metered controllers call `ReportExportMeteringService::recordExport()` **only
after** the export succeeds. Recording is idempotent (an explicit
`Idempotency-Key` header, otherwise a deterministic tenant+route+user+report+
filter+minute fingerprint), so a retried export within the same minute collapses
to one event (EGC-R008). A suspended/unentitled/over-quota request is blocked by
middleware and never reaches the recorder; a failure while building the export
throws before recording — so blocked/failed exports never count (EGC-R007).
Metadata passes `SanitizesUsageEventMetadata` and holds only non-sensitive
context (report type, format, route, normalized filters) (EGC-R009).

## Platform admin visibility (read-only, EGC-R011/R012)

- `GET /api/v1/admin/export-governance/routes` — registered + discovered routes.
- `GET /api/v1/admin/export-governance/coverage-summary` — discovered / registered
  / metered / exempt / gap counts and lists.
- `GET /api/v1/admin/export-governance/metering-summary` — meter status + gaps.

All three are behind `platform.admin`, read-only, and describe **route
governance**, never tenant usage. There is deliberately **no** route that
bypasses metering, overrides a usage limit, or mutates the usage ledger.

## Commands / gate

- `export-governance:route-scan` — discovery (informative; `--strict` fails on any
  unregistered export-like route).
- `export-governance:coverage-summary` — redacted coverage counts + lists.
- `export-governance:metering-audit` — enforcement audit; non-zero on any critical
  gap.
- `export-governance:go-no-go` — Sprint 29 release gate (EGC-R014): audit + command
  contract + Sprint 25/26/27/28 prior gates + meterable + docs.

## Interaction with prior sprints

- **Sprint 27** — `tenant_usage_events` stays append-only; `reports.exports.monthly`
  stays `meterable: true`; the daily-sales export stays metered; retry stays
  idempotent; `report-export-metering:go-no-go` stays green.
- **Sprint 28** — anomaly detection stays read-only; governed repair stays
  CLI-only; effective usage = `max(0, ledger_count + Σ repair_delta)`;
  `usage-ledger:go-no-go --strict` stays GO. No usage-ledger update/delete route
  is added.
- **Sprint 26** — usage-limit enforcement reads effective usage; `tenant-plan:go-no-go`
  stays green.
- **Sprint 25** — lifecycle runs first; suspended tenant → 423 `TENANT_SUSPENDED`.
- **Sprint 24** — renewal/dunning untouched.
