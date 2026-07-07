# Demo Tenant Pilot Setup Evidence

Sprint 15 — Pilot Deployment & Field Trial Evidence Foundation.

Records that the pilot demo tenant was provisioned using the Sprint 12 tenant
onboarding + demo data foundation. Placeholders only — **no passwords or real
customer data**.

Tenant: `DEMO_TENANT_PLACEHOLDER` · Owner: `owner@example.test` ·
Operator: `operator@example.test`.

| # | Check | Reference | Result |
|---|-------|-----------|--------|
| 1 | Tenant onboarding | `POST /api/v1/admin/tenant-onboarding` (idempotent) | ☐ |
| 2 | Default store | Store created for tenant | ☐ |
| 3 | Owner user | Owner role provisioned (placeholder credential) | ☐ |
| 4 | Operator user | Operator role provisioned (placeholder credential) | ☐ |
| 5 | Subscription status | Active via `/api/v1/subscription/status` | ☐ |
| 6 | Registered device | Device registered within limit | ☐ |
| 7 | Demo products | Demo catalog seeded (tenant-isolated) | ☐ |
| 8 | Opening inventory | Opening stock movements present | ☐ |
| 9 | Report/closing | Daily report + closing reachable | ☐ |
| 10 | Reset guard | Demo reset guard verified (manifest-based) | ☐ |

## Notes

- Credentials in this document must remain placeholders.
- Onboarding is platform-admin only; no public signup.
- Demo data is tenant-isolated and safe to reset within the demo tenant only.
