# Lifecycle Precedence & Sprint 24/25 Coexistence (Sprint 26)

`TPE-R004` / `TPE-R005` / `TPE-R012`.

## Guard order

On every protected operational route:

```
auth:sanctum → tenant.active → tenant.context → subscription.active →
tenant.lifecycle → device.registered → tenant.entitled:<feature> → tenant.usage.limit:<key>
```

`tenant.lifecycle` (Sprint 25) is the **first governance gate**. The entitlement
and usage guards run after it, verified by `tenant-plan:enforcement-audit`
(`lifecycle_precedence` signal checks the lifecycle alias appears before any plan
guard on every guarded route).

## Precedence outcomes

| Tenant state | Response |
|---|---|
| Manually suspended / status suspended | **423** `TENANT_SUSPENDED` (Sprint 25) |
| Archived (inactive) | **423** `TENANT_ARCHIVED` (Sprint 25) |
| Active, subscription lapsed | **402** subscription blocked (Sprint 10/24) |
| Active, entitled but feature off | **403** `FEATURE_NOT_ENTITLED` |
| Active, entitled but limit reached | **429** `USAGE_LIMIT_EXCEEDED` |
| Active, entitled, within limit | pass |

A suspended tenant with a valid enterprise plan is **still** `TENANT_SUSPENDED`.
An entitlement override or plan assignment can **never** re-enable a
suspended/cancelled/archived tenant (`TPE-R005`).

## Coexistence with Sprint 24 (renewal/dunning)

- Plan assignment never mutates `tenant_subscriptions`, renewal candidates, or
  dunning notices. Subscription renewal/dunning never writes plan assignments or
  entitlement overrides.
- Dunning cannot bypass entitlement/usage enforcement.

## Coexistence with Sprint 25 (lifecycle/suspension)

- Manual suspension precedence over automation (`TLS-R004`) is unchanged.
- `TLS-R001..R010` remain locked; `suspended` remains a blocked lifecycle status.
  `tenant-plan:readiness` FAILs if any TLS rule is dropped or `suspended` is no
  longer blocked (`TPE-R012`).
- `tenant-lifecycle:go-no-go` remains green; Sprint 26 adds a parallel gate, it
  does not replace the lifecycle gate.
