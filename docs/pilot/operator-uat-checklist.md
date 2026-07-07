# Operator UAT Checklist

Sprint 14 — Pilot Release Candidate & Operator UAT Foundation.

This checklist is executed by a **store operator** on a real device against a
**demo tenant** during the pilot. It validates that the day-to-day cashier and
admin flows behave correctly end to end.

> Credentials are **placeholders**. Use a secure temporary password issued by a
> platform admin for the demo tenant. Never write real passwords, payment
> gateway secrets, or production customer data into this file.

## Test context (placeholders)

| Field | Value |
|-------|-------|
| Tenant | `DEMO_TENANT_PLACEHOLDER` |
| Operator email | `operator@example.test` |
| Password | use secure temporary password from admin, not stored here |
| Device | record device model in the UAT result |
| App version | record `versionName` / `versionCode` in the UAT result |

## Scenarios

Mark each: **PASS**, **WATCH** (works with minor issue), or **FAIL**. Log any
non-PASS result in [`issue-register.md`](issue-register.md).

1. [ ] Login with demo tenant user (`operator@example.test`).
2. [ ] Tenant context loaded (store name / tenant shown correctly).
3. [ ] Product sync works (catalog downloads to device).
4. [ ] Product search works (find item by name).
5. [ ] Add item to cart.
6. [ ] Update cart quantity.
7. [ ] Cash sale success (totals correct, sale recorded).
8. [ ] Receipt preview success.
9. [ ] Printer test / print receipt.
10. [ ] QRIS payment status screen check (pending → status transitions).
11. [ ] Offline cash sale creation (airplane mode / no network).
12. [ ] Offline sync after network returns (queued sale uploads).
13. [ ] Inventory stock visibility after sale/sync (stock decremented).
14. [ ] Daily sales report check.
15. [ ] Daily closing check.
16. [ ] Subscription/device blocked-state check (blocked device is denied).
17. [ ] Admin onboarding / demo tenant check (admin can onboard demo tenant).
18. [ ] Demo reset guard check (reset only affects demo tenant data).

## Sign-off

- Operator: ________________________  Date: ____________
- Result summary: PASS ___ / WATCH ___ / FAIL ___
- Linked issues: see [`issue-register.md`](issue-register.md)

Feed structured results into `php artisan pilot:uat-summary` via the optional
`docs/pilot/uat-result.json` (demo/placeholder data only).
