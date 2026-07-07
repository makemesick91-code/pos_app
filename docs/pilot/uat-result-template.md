# Operator UAT Result Template

Sprint 14 — Pilot Release Candidate & Operator UAT Foundation.

Copy this template per UAT session. Fill placeholders with **demo tenant** data
only — no real customer data, no real credentials.

## Session metadata

| Field | Value |
|-------|-------|
| UAT date | ____________ |
| Tester / operator | ____________ (e.g. `operator@example.test`) |
| Device model | ____________ |
| Android version | ____________ |
| App versionName | ____________ |
| App versionCode | ____________ |
| Tenant / store | `DEMO_TENANT_PLACEHOLDER` |

## Scenario results

Status: `PASS` · `WATCH` · `FAIL` · `PENDING`.

| # | Scenario | Status | Notes | Issue ID |
|---|----------|--------|-------|----------|
| 1 | Login & tenant context | | | |
| 2 | Product sync | | | |
| 3 | Product search | | | |
| 4 | Cashier cart (add / update qty) | | | |
| 5 | Cash sale | | | |
| 6 | Receipt preview | | | |
| 7 | Printer check | | | |
| 8 | QRIS payment status | | | |
| 9 | Offline cash sale | | | |
| 10 | Offline sync retry | | | |
| 11 | Inventory stock movement | | | |
| 12 | Daily report | | | |
| 13 | Daily closing | | | |
| 14 | Subscription/device gate | | | |
| 15 | Admin onboarding / demo tenant | | | |
| 16 | Demo data reset guard | | | |

## Evidence

- Screenshots / recordings: _attach externally; reference file names here_.
- Logs (redacted): ____________

## Issues

List issues raised this session (link to [`issue-register.md`](issue-register.md)):

- ____________

## Operator sign-off

- Name: ________________________
- Signature / confirmation: ________________________
- Overall: PASS ___ / WATCH ___ / FAIL ___

## Optional machine-readable result

For `php artisan pilot:uat-summary`, place a `docs/pilot/uat-result.json` with:

```json
{
  "scenarios": { "login": "PASS", "cash_sale": "PASS" },
  "issues": [ { "severity": "MINOR", "status": "OPEN" } ]
}
```

Demo/placeholder data only.
