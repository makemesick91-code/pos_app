# Usage Limit Governance (Sprint 26)

`TPE-R003` — usage limits are evaluated by a central service before protected
mutations.

## Registry

Usage-limit keys are declared in `config/tenant_plan.usage_limits`:

| Key | Period | Meterable now | Current-usage source |
|---|---|---|---|
| `branches.max` | lifetime | yes | `stores` count per tenant |
| `users.max` | lifetime | yes | `users` count per tenant |
| `devices.max` | lifetime | yes | ACTIVE `registered_devices` per tenant |
| `products.max` | lifetime | yes | `products` count per tenant |
| `transactions.monthly` | monthly | yes | `sales` this month per tenant |
| `reports.exports.monthly` | monthly | **deferred** | not yet metered |

Each plan sets a numeric cap or `unlimited` (`plan_usage_limits`). Enterprise is
unlimited on every limit.

## Decision

`TenantUsageLimitService::canUse(tenant, key, increment=1)` → `UsageLimitDecision`:

- Unlimited plan or limit not configured for the plan → allowed.
- Meterable limit: `current = TenantUsageMeter::currentUsage(...)` (real DB count);
  allowed iff `current + increment <= cap`. Exceeded → stable code
  `USAGE_LIMIT_EXCEEDED` (`TPE-R009`).
- Declared-but-deferred limit (`reports.exports.monthly`): reported explicitly
  (`meterable: false`), never a silent zero, and allowed at runtime.

Current usage is derived from DB on read — no fragile stored counters.

## Runtime enforcement

`tenant.usage.limit:<key>` middleware on real mutations:

| Mutation | Limit |
|---|---|
| `POST /api/v1/products` | `products.max` |
| `POST /api/v1/sales` | `transactions.monthly` |

Exceeded response: HTTP **429**

```json
{ "message": "Usage limit exceeded for this tenant plan.", "code": "USAGE_LIMIT_EXCEEDED", "limit": "products.max" }
```

Runs after `tenant.lifecycle` and `tenant.entitled` (`TPE-R004`): suspended →
`TENANT_SUSPENDED`, unentitled → `FEATURE_NOT_ENTITLED`, only then usage is
checked.

## Deferred meters

`reports.exports.monthly` is a declared foundation limit whose live metering is
deferred to a later sprint (report-export events are not yet persisted). It is
reported as `meterable: false` by the summary/limit views and never blocks.
