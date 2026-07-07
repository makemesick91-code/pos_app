# SaaS Package Catalog — Aish POS Lite

Sprint 20. Internal/admin-only commercial package definitions. Persisted in
`saas_package_catalogs` and managed via `/api/v1/admin/saas-packages`.

> **No billing automation rule.** Package pricing is governance metadata only. A
> package activates **no** real billing, opens **no** public signup, and never
> bypasses the `SubscriptionPlan` / `TenantSubscription` / `RegisteredDevice`
> runtime enforcement from Sprint 10.

## Package fields

| Field | Meaning |
| --- | --- |
| `package_code` | Unique internal code (e.g. `PKG-WARUNG-LITE`) |
| `name` | Display name |
| `target_segment` | WARUNG / TOKO_KECIL / KEDAI / LAUNDRY / RETAIL / APOTEK_LIGHT / GENERAL_UMKM |
| `monthly_price` + `currency` | Governance price metadata (default IDR) |
| `device_limit` | Governance device cap (runtime enforcement stays in RegisteredDevice) |
| `store_limit` / `user_limit` | Governance caps |
| `onboarding_level` | SELF_GUIDED / ASSISTED / MANAGED |
| `support_level` | BASIC / STANDARD / PRIORITY |
| `included_modules` / `excluded_modules` | Feature boundaries (metadata) |
| `feature_flags` | Metadata flags only |
| `evidence_reference` | Link to supporting evidence |
| `status` | DRAFT / REVIEW / ACTIVE / RETIRED / BLOCKED |

## Example catalog (template)

| Code | Segment | Device limit | Support | Onboarding | Included modules |
| --- | --- | --- | --- | --- | --- |
| PKG-WARUNG-LITE | WARUNG | 1 | BASIC | SELF_GUIDED | cashier, cash, receipt |
| PKG-UMKM-STARTER | GENERAL_UMKM | 2 | STANDARD | ASSISTED | cashier, cash, qris, inventory, reports |
| PKG-RETAIL-PRO | RETAIL | 3 | PRIORITY | MANAGED | all MVP modules |

## Lifecycle

`DRAFT → REVIEW → ACTIVE` (via `/approve`) → `RETIRED` (via `/retire`). `BLOCKED`
marks a package that must not be offered. Only `ACTIVE` packages count toward
launch readiness. At least one ACTIVE package must cover the `GENERAL_UMKM`
required segment.
