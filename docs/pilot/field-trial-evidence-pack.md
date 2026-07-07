# Field Trial Evidence Pack

Sprint 15 — Pilot Deployment & Field Trial Evidence Foundation.

Canonical container that references every piece of field trial evidence for one
pilot. Use placeholders only — no real credentials or customer data.

## Pilot candidate

| Field | Value (placeholder) |
|-------|---------------------|
| Candidate commit | `PILOT_COMMIT_PLACEHOLDER` |
| GO tag candidate | `sprint-15-pilot-deployment-field-trial-evidence-foundation-go` |
| Field trial date | `YYYY-MM-DD` |
| Tenant / store | `DEMO_TENANT_PLACEHOLDER` / `DEMO_STORE_PLACEHOLDER` |
| Operator | `operator@example.test` |
| Device model | `DEVICE_MODEL_PLACEHOLDER` |
| Network condition | `NETWORK_NOTES_PLACEHOLDER` (wifi / 4G / intermittent) |

## Evidence references

Each field trial evidence category maps to a canonical document. The
`pilot:field-trial-summary` command enumerates the same categories.

| Category | Evidence document |
|----------|-------------------|
| Backend deployment dry-run | `backend-deployment-dry-run.md` |
| Android RC artifact handling | `android-rc-artifact-handling.md` |
| Demo tenant pilot setup | `demo-tenant-pilot-setup-evidence.md` |
| Operator device readiness | `operator-device-readiness.md` |
| Post-deploy smoke | `post-deploy-smoke-checklist.md` |
| Offline cash field check | `post-deploy-smoke-checklist.md` (offline scenarios) |
| QRIS status field check | `post-deploy-smoke-checklist.md` (QRIS scenario) |
| Receipt/printer field check | `post-deploy-smoke-checklist.md` (receipt/printer) |
| Inventory/report/closing field check | `post-deploy-smoke-checklist.md` (reports/closing) |
| Subscription/device gate field check | `post-deploy-smoke-checklist.md` (subscription/device) |
| Rollback readiness | `pilot-rollback-checklist.md` |
| Daily monitoring readiness | `daily-pilot-monitoring-checklist.md` |
| Field issue register | `field-issue-register.md` |

## Linked artifacts

- Issue register: `field-issue-register.md`
- Daily monitoring log: `daily-pilot-monitoring-checklist.md`
- GO/WATCH/NO-GO report: `field-trial-go-watch-no-go-report.md`

## Decision

| Item | Result |
|------|--------|
| Field trial summary | `php artisan pilot:field-trial-summary --json` |
| Open BLOCKER/CRITICAL issues | `0` for GO |
| Decision | GO / WATCH / NO-GO |
| Approver | `APPROVER_PLACEHOLDER` |
