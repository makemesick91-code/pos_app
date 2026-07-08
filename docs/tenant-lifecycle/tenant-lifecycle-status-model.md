# Tenant Lifecycle Status Model (Sprint 25)

The canonical, server-side-only lifecycle status vocabulary. Computed only by
`TenantLifecycleService`; never trusted from client input (TLS-R001).

| Status       | Meaning                                                        | Operational access |
|--------------|----------------------------------------------------------------|--------------------|
| `onboarding` | Tenant is being provisioned.                                   | Allowed            |
| `active`     | Healthy, subscription allowed.                                 | Allowed            |
| `grace`      | Subscription in grace window (awareness).                      | Allowed            |
| `past_due`   | Subscription expired/cancelled (awareness; 402 by billing).    | Allowed by lifecycle guard |
| `suspended`  | Manually suspended OR `tenant.status = suspended`.             | **Blocked (423)**  |
| `cancelled`  | Reserved for cancelled lifecycle.                              | **Blocked**        |
| `archived`   | `tenant.status = inactive` / archived tenant.                  | **Blocked**        |

The **blocked set** (`suspended`, `cancelled`, `archived`) is what the runtime
`tenant.lifecycle` guard denies.

## Mapping rules

`TenantLifecycleService::resolve(Tenant)`:

1. Active manual suspension → `suspended` (source `manual_suspension`). Highest
   precedence (TLS-R004).
2. `tenant.status = suspended` → `suspended` (source `tenant_status`).
3. `tenant.status = inactive` → `archived` (source `tenant_status`).
4. Otherwise refine from `SubscriptionStatusService`: `grace → grace`,
   `expired/cancelled/suspended → past_due`, else `active` (source
   `subscription`).

## Decision object

`TenantLifecycleDecision` is immutable and carries `status`, `allowed`, `code`,
`reason`, `source`, `manuallySuspended`, `manualSuspensionId`. `code` is one of
`TENANT_SUSPENDED`, `TENANT_ARCHIVED`, or `null` when allowed.
