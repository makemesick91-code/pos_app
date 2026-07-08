# Feature Entitlement Governance (Sprint 26)

`TPE-R002` — feature entitlement is enforced server-side and must not rely on
Android/UI visibility.

## Registry

Entitlement keys are declared in `config/tenant_plan.entitlements`:

`pos.sales`, `pos.refunds`, `pos.discounts`, `inventory.basic`,
`inventory.advanced`, `reports.basic`, `reports.advanced`, `users.manage`,
`devices.manage`, `branches.manage`, `billing.view`.

Each plan enables a subset (`plan_entitlements`). `pos.sales`, `inventory.basic`,
and `reports.basic` are granted by every plan (including the restricted default),
so the base POS surface always works; higher features are plan-gated.

## Decision

`FeatureEntitlementService::decide(tenant, feature)` → `EntitlementDecision`:

1. Base = plan grant for the feature (`false` if the plan does not enable it).
2. An ACTIVE `tenant_entitlement_overrides` row for the feature refines it up or
   down (`source = override`).
3. Denied → stable code `FEATURE_NOT_ENTITLED` (`TPE-R008`).

`TenantEntitlementGuard` is the single guard reused by the middleware and the
enforcement audit.

## Runtime enforcement

`tenant.entitled:<feature>` middleware, applied to operational route groups:

| Feature | Guarded routes |
|---|---|
| `inventory.basic` | products, product-categories, product-store-prices, sync, inventory/* |
| `pos.sales` | sales, receipt, QRIS/cash payments |
| `reports.basic` | reports/*, daily closings |

Denied response: HTTP **403**

```json
{ "message": "This feature is not available on the tenant plan.", "code": "FEATURE_NOT_ENTITLED", "feature": "reports.advanced" }
```

The guard runs **after** `tenant.lifecycle` (`TPE-R004`), so a suspended tenant is
blocked with `TENANT_SUSPENDED` first and an override can never re-enable it
(`TPE-R005`).

## Overrides (platform admin only)

`POST /api/v1/admin/tenants/{tenant}/entitlement-overrides` — reason mandatory and
sanitized, audit-logged (`TPE-R006`, `TPE-R007`). `TenantEntitlementOverrideService`
is the only writer; it never charges and never bypasses lifecycle enforcement.
