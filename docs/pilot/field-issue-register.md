# Field Issue Register

Sprint 15 — Pilot Deployment & Field Trial Evidence Foundation.

Authoritative register of issues observed during the field trial. Placeholders
only — no real credentials or customer data.

## Severity

- `BLOCKER` — pilot cannot continue; forces NO-GO.
- `CRITICAL` — core flow broken; forces NO-GO.
- `MAJOR` — significant but has workaround; normally WATCH.
- `MINOR` — small defect; can GO if documented.
- `TRIVIAL` — cosmetic; can GO if documented.

## Status

`OPEN` · `IN_PROGRESS` · `FIXED` · `RETEST` · `CLOSED` · `ACCEPTED_RISK`

## Gating rules

- Any open `BLOCKER`/`CRITICAL` => **NO-GO**.
- Any open `MAJOR` => **WATCH** unless explicitly accepted as risk.
- `MINOR`/`TRIVIAL` => may **GO** when documented.

## Register

| ID | Date | Field Area | Severity | Blocking | Title | Device | Tenant/Store | Steps | Expected | Actual | Owner | Status | Fix Sprint/PR | Evidence |
|----|------|------------|----------|----------|-------|--------|--------------|-------|----------|--------|-------|--------|---------------|----------|
| FI-000 | YYYY-MM-DD | example | TRIVIAL | no | Example placeholder row | DEVICE_MODEL_PLACEHOLDER | DEMO_TENANT_PLACEHOLDER | steps | expected | actual | OWNER_PLACEHOLDER | CLOSED | — | — |

## Structured export (optional)

The `pilot:field-trial-summary` command can read
`docs/pilot/field-trial-result.json` (not committed with real data) shaped as:

```json
{ "issues": [ { "severity": "BLOCKER", "status": "OPEN" } ] }
```

Any open BLOCKER/CRITICAL entry forces the field trial summary to NO-GO.
