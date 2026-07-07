# Pilot Issue Register

Sprint 14 — Pilot Release Candidate & Operator UAT Foundation.

Single source of truth for defects and observations found during pilot smoke and
operator UAT. Every non-PASS scenario result must produce a row here.

> No real credentials, payment gateway secrets, or production customer data. Use
> demo tenant / placeholder references only.

## Severity

| Severity | Meaning |
|----------|---------|
| BLOCKER  | Pilot cannot proceed; core flow broken or data loss. |
| CRITICAL | Major flow broken, no safe workaround. |
| MAJOR    | Important issue with a workaround. |
| MINOR    | Small defect, limited impact. |
| TRIVIAL  | Cosmetic / wording. |

## Status

`OPEN` · `IN_PROGRESS` · `FIXED` · `RETEST` · `CLOSED` · `ACCEPTED_RISK`

## Gating rules

- An **OPEN / IN_PROGRESS / RETEST** `BLOCKER` or `CRITICAL` issue forces **NO-GO**.
- An open `MAJOR` issue is normally **WATCH** unless explicitly accepted (`ACCEPTED_RISK`).
- `MINOR` / `TRIVIAL` may still **GO** when documented.

These rules are enforced by `php artisan pilot:uat-summary` when a structured
`uat-result.json` is provided.

## Register

| ID | Date | Area | Severity | Blocking | Title | Steps | Expected | Actual | Owner | Status | Fix Sprint/PR | Evidence |
|----|------|------|----------|----------|-------|-------|----------|--------|-------|--------|---------------|----------|
| _example_ | 2026-07-07 | Cashier | MINOR | no | Receipt footer wording | Print receipt | Correct footer | Typo in footer | _unassigned_ | OPEN | — | — |

_Add rows below. Keep the example row for format reference or remove it before
the pilot._
