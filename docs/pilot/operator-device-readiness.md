# Operator Device Readiness

Sprint 15 — Pilot Deployment & Field Trial Evidence Foundation.

Confirms a pilot operator device is ready before the field trial. Use
placeholders — no real device identifiers tied to a person are required.

Operator: `operator@example.test` · Tenant: `DEMO_TENANT_PLACEHOLDER`.

| # | Check | Expected | Result |
|---|-------|----------|--------|
| 1 | Device model | `DEVICE_MODEL_PLACEHOLDER` recorded | ☐ |
| 2 | Android version | >= 8.0 (API 26) | ☐ |
| 3 | Storage | >= 500 MB free | ☐ |
| 4 | Battery | >= 50% or charger available | ☐ |
| 5 | Network | Wi-Fi/4G reachable; offline fallback tested | ☐ |
| 6 | Printer pairing | Bluetooth/USB thermal printer paired | ☐ |
| 7 | App install | `com.aishtech.poslite` installed (CI artifact) | ☐ |
| 8 | Device registration | Device registered under tenant (backend enforced) | ☐ |
| 9 | Subscription/device gate | Subscription active + device within limit | ☐ |
| 10 | Offline mode | Airplane-mode cash sale queues and syncs later | ☐ |

## Sign-off

- Prepared by: `PREPARER_PLACEHOLDER`
- Date: `YYYY-MM-DD`
- Blocking issues logged in `field-issue-register.md`: yes/no
