# Usage Ledger Anomaly Detection & Governed Repair Governance (Sprint 28)

This document is the architecture reference for Sprint 28. It builds on the
Sprint 27 append-only usage event ledger
(`docs/architecture/report-export-metering-usage-event-ledger-governance.md`).

## Goal

Give the platform a safe way to (1) **detect** anomalies in the append-only
`tenant_usage_events` ledger and (2) **repair** effective usage **without ever
mutating or deleting an original ledger event**. Detection is read-only; repair is
CLI-only, dry-run by default, explicit, auditable, and redacted.

Canonical rules `ULR-R001..R016` are locked in
`backend/config/usage_ledger_anomaly.php` and mirrored in `docs/PROJECT_RULES.md`.

## Dependency graph

```
tenant_usage_events (append-only ledger, Sprint 27)
   │  read-only
   ▼
UsageLedgerAnomalyRepository ── read queries ──► UsageLedgerAnomalyDetector ──► UsageLedgerAnomaly (redacted)
                                                        │                         │
                                                        ▼                         ▼
                                             UsageLedgerAnomalySummary     UsageLedgerRepairPlanner
                                                        │                         │  (dry-run, no writes)
                                    admin GET /usage-ledger/anomalies             ▼
                                    admin GET /tenants/{t}/usage-ledger/anomalies UsageLedgerRepairDecision
                                    usage-ledger:anomaly-scan                     │
                                                                                  ▼
                                                              UsageLedgerRepairService.apply(--apply,--reason,--actor)
                                                                                  │  append-only correction
                                                                                  ▼
                                                              tenant_usage_ledger_repairs (signed quantity_delta)
                                                                                  │            │
                                                                    AdminAuditLogger.log       ▼
                                                                                  UsageEventLedgerService.meterCount
                                                                                  = max(0, ledger count + repair deltas)
                                                                                  │
                                                              TenantUsageMeter → TenantUsageLimitService → enforcement
```

## Anomaly types & severity

| Type | Severity | Auto-repairable | Notes |
|------|----------|-----------------|-------|
| `duplicate_idempotency` | critical | **yes** | Same tenant + meter + period + request_fingerprint appears >1× → double-count drift. Correction collapses the group to one logical event. The DB unique `(tenant_id, idempotency_key)` already blocks exact key dupes, so drift appears as distinct keys with one fingerprint. |
| `unsanitized_metadata` | critical | no | Metadata carries a secret-looking key (`password`, `token`, `secret`, `credential`, `authorization`, `card`, `cvv`, `payment_key`, …). Only the **key name** is reported, never the value. |
| `missing_required_field` | warning | no | `event_key`/`event_category`/`period_key`/`occurred_at` empty, or empty-string `meter_key`. |
| `invalid_quantity` | warning | no | quantity `< 1`. |
| `invalid_period` | warning | no | monthly meter with `period_key` not in `Y-m`, or `occurred_at` month ≠ `period_key`. |
| `unknown_meter` | warning | no | `meter_key` absent from the canonical `config/tenant_plan.usage_limits` registry. |

Cross-tenant leakage is prevented structurally: anomaly/repair summaries are only
exposed behind `platform.admin` and the per-tenant endpoint is scoped to the path
tenant (`ULR-R012`).

## Governed repair strategy

The ledger `quantity` column is `unsignedInteger`, so a negative correction event
cannot be stored there. Instead, Sprint 28 uses a **governed repair record** table
`tenant_usage_ledger_repairs` with a signed `quantity_delta` (the spec's
"alternative when the schema does not support negative correction").

- **Effective usage** = `max(0, ledger_count + Σ repair_delta)` for a
  `(tenant, meter, period)`. Implemented in `UsageEventLedgerService::meterCount()`
  (with `rawMeterCount()` and `repairDelta()` helpers). `TenantUsageMeter` and
  therefore `TenantUsageLimitService` enforcement read this effective value.
- **Append-only preserved**: original events are never updated or deleted
  (`ULR-R010`).
- **Non-negative**: `apply()` clamps `delta` to `max(delta, -base)` and
  `meterCount()` clamps at 0 (`ULR-R013`).
- **Idempotent**: `repair_key` is a deterministic hash of the anomaly signature and
  unique per tenant; re-applying skips existing records (`ULR-R011`).

### Dry-run / apply safety (`usage-ledger:repair-apply`)

- Refuses to run without `--apply` **or** `--dry-run`.
- Always requires `--reason` and `--actor`.
- `--apply` writes governed correction records; `--dry-run` simulates with no DB
  writes.
- Only `apply` decisions (duplicate correction) are written; `manual_review`
  decisions are skipped.

### Audit & redaction

Each applied repair is itself an auditable governed record (reason, applied_by,
applied_at, redacted metadata). When a platform-admin user exists, the apply also
writes an `admin_audit_logs` entry (`ACTION_USAGE_LEDGER_REPAIR_APPLIED`). All
metadata passes `SanitizesUsageEventMetadata` / `AdminAuditLogger::sanitize()` so
no secret is ever persisted (`ULR-R006`, `ULR-R008`).

## No runtime mutation route

There is **no** API route that creates, updates, or deletes usage ledger events or
repair records. The only admin surfaces are read-only GET summaries. Governed
repair is CLI-only (`ULR-R009`). `usage-ledger:go-no-go` fails if any mutating
route touching `usage-events`/`usage-ledger` is ever added.

## Interaction with prior sprints

- **Sprint 27**: `reports.exports.monthly` stays `meterable: true` and metered
  from the ledger; successful export records exactly one event, retries do not
  double count, blocked/failed exports record nothing (`ULR-R014`).
- **Sprint 26**: usage-limit enforcement is unchanged except that it now reads
  effective usage (ledger + governed repairs). Unlimited plans stay unblocked;
  numeric limits still block at cap.
- **Sprint 25**: tenant lifecycle enforcement still runs first; a suspended tenant
  stays blocked.

## Release gate

`usage-ledger:go-no-go` is the Sprint 28 release gate (`ULR-R015`). It also
re-runs `report-export-metering:go-no-go` (Sprint 27), `tenant-plan:go-no-go`
(Sprint 26), and `tenant-lifecycle:go-no-go` (Sprint 25).
