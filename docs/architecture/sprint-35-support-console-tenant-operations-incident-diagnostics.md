# Sprint 35 — Support Console, Tenant Operations & Incident Diagnostics

## Scope

Make the SaaS operationally supportable for real tenants **without opening the
database directly**. Sprint 35 adds a platform-admin support console covering:

- tenant health overview
- billing / payment / entitlement / onboarding / device / sync status
- a deterministic diagnostic timeline
- a governed device revoke/reactivate support flow
- sync failure inspection
- a blocked/denied action explorer
- an invoice/payment/collection status viewer
- a support-safe, read-only tenant context (time-bound)
- impersonation — **disabled by default** and strictly governed
- tenant-isolated incident notes
- a support audit trail
- no PII/secret leakage anywhere

It is **additive** and backward compatible. It does not change Sprint 23–34
semantics, does not mutate Sprint 23 `saas_billing_*` behaviour, and does not
weaken Sprint 30 invoice/collection, Sprint 31 gateway settlement, Sprint 32
entitlement enforcement, Sprint 33 onboarding lifecycle, or Sprint 34 Android
runtime / device / sync semantics.

## Non-goals

- No React/Vue/heavy frontend; the surface is a minimal JSON admin API + CLI.
- No Android support-console UI (Android is untouched functionally).
- No external network / real payment-gateway dependency in CI.
- No VPS deploy.
- No new billing/payment/entitlement/onboarding/device/sync tables — Sprint 35
  **reads** the existing ledgers and adds only support-specific records.

## Commercial SaaS chain

```
Plan → Invoice → Payment Intent → Gateway Settlement → Collection →
Entitlement Runtime Access → Tenant Onboarding → Android Runtime → Support Operations
```

Support Operations is a pure **observer + governed operator** at the end of the
chain. It never writes upstream state except through the trusted upstream
services (only Sprint 34 device revocation today).

## Support operations architecture

```
                       ┌─────────────────────────────────────────┐
 platform.admin  ──►   │ AdminSupportConsole/Device/Incident/     │
 (api/v1/admin/        │ Session controllers  (READ-ONLY default) │
  support-ops/*)       └───────────────┬─────────────────────────┘
                                       │
        ┌──────────────────────────────┼──────────────────────────────┐
        ▼                              ▼                              ▼
 SupportTenantHealthService   Support*ViewerService (5)      Support{Device,Incident,
 (aggregates all viewers)     billing/payment/entitlement/   ReadOnlyContext,Impersonation}
        │                     onboarding/androidRuntime      Service (governed mutations)
        ▼                              │                              │
 SupportDiagnosticTimeline            │                     SupportAuditService ─► tenant_support_actions
 Service (deterministic merge)         │                              │            + admin_audit_logs
        └───────────── reads Sprint 30/31/32/33/34 ledgers ───────────┘
                        (never mutated by support)              SupportRedactor (all output)
```

Governance is declared in `config/support_operations_governance.php` and mirrored
into `config/pos_foundation.php` + `docs/PROJECT_RULES.md`. `SupportGovernanceAuditService`
and `SupportGoNoGoService` assert the wiring; the `support-ops:*` commands surface it.

## Tenant health model (`SupportTenantHealthService`, SUP-R002/R014)

Health is one of `healthy < watch < degraded < blocked < critical`. The service
escalates to the worst applicable status:

| Condition | Status |
|---|---|
| Active manual suspension (Sprint 25) | `critical` (always wins) |
| Unpaid invoice past grace (overdue/failed, due + grace_days in the past) | `blocked` |
| Unpaid in grace / pending | `degraded` |
| Latest provisioning run failed/cancelled | `degraded` |
| Revoked device present | `watch` |
| Sync batch/item failures present | `watch` |

Reason codes are safe enums (e.g. `manual_suspension_active`, `unpaid_past_grace`,
`sync_failures_present`). The overview embeds the five read-only viewer summaries.

## Diagnostic timeline model (`SupportDiagnosticTimelineService`, SUP-R020)

Merges events from: onboarding runs, invoices, payment intents, gateway events,
entitlement decisions, device activations, sync batches, sync conflicts, incidents,
support actions and (safe) admin audit logs. Each event carries `{source,
category, at, code, summary, ref_id}` only — never a raw payload/token/PII (every
summary passes through `SupportRedactor::redactText`). Ordering is deterministic:
timestamp desc, then source, then ref_id. Filters: `category`, `source`, `since`,
`limit` (bounded by config).

## Billing / payment viewer model (SUP-R008/R009/R015)

`SupportBillingViewerService` reads `tenant_billing_invoices` (Sprint 30) and
reports counts by status/collection_state + outstanding amount + latest invoices.
`SupportPaymentViewerService` reads `tenant_billing_payment_intents` /
`tenant_billing_gateway_events` (Sprint 31) and reports counts by status +
`signature_verified` flags. Neither returns raw signatures/payloads, marks an
invoice paid, or replays a settlement.

## Entitlement blocked-action explorer (SUP-R010/R021)

`SupportEntitlementViewerService` reads the Sprint 32 `tenant_entitlement_decisions`
audit ledger only (via `scopeForTenant`). It never calls `EntitlementAccessService`
to make/alter a decision — it surfaces denied/read_only/degraded decisions with
safe reason codes.

## Onboarding viewer model (SUP-R011)

`SupportOnboardingViewerService` reads the Sprint 33 `tenant_provisioning_runs`
and reports status counts + latest run. It does not mutate the provisioning
lifecycle; retry/cancel remain the Sprint 33 governed services' responsibility.

## Android runtime / sync diagnostics (SUP-R022)

`SupportAndroidRuntimeViewerService` reads `tenant_device_activations`,
`tenant_android_sync_batches`, `tenant_android_sync_items` (Sprint 34) via each
model's `toSafeArray`, and inspects sync failures (failed/partial_failed/rejected
batches, failed/rejected/conflict items). No raw sync payload is returned.

## Device revoke/reactivate policy (SUP-R012/R013)

`SupportDeviceOperationsService::revoke` delegates to the Sprint 34
`DeviceRevocationService` (blocks future sync/write, moves the paired
`RegisteredDevice` to REVOKED). Reactivation is **disabled by default**
(`device_operations.reactivate_enabled=false`) — the attempt is audited DENIED and
a governed `SUPPORT_REACTIVATION_NOT_SUPPORTED` (409) is returned, so a device is
re-enabled only through the standard Sprint 34 activation flow, which re-runs the
entitlement/device-limit gate. Manual suspension always wins.

## Read-only context policy (SUP-R017)

`SupportReadOnlyContextService::start` records a time-bound (`expires_at`),
tenant-scoped session that grants **no** mutation power — it is an audit + scoping
record only. Expiry is enforced lazily by `assertEffective`/`isEffective` (no
background job). Start/end/denied are audited. `scope_json` holds only
`{read_only:true, tenant_id}` — never a credential.

## Impersonation policy — why disabled by default (SUP-R018/R019)

Borrowing a tenant user's identity is **not required** for any safe
support-visibility need: the read-only context above covers every diagnostic use
case without ever assuming a tenant identity or exposing a credential. Therefore
`impersonation.enabled=false`. `SupportImpersonationService::start` records a
governed DENIED session + support action and throws
`SUPPORT_IMPERSONATION_DISABLED` (403). It never produces or persists a raw
credential/token. A future governed implementation would still have to be
platform.admin-only, read-only-safe, time-bound, audited, and credential-free —
the governance audit enforces exactly that shape.

## Incident notes model (SUP-R023/R024)

`tenant_support_incidents` + `tenant_support_incident_notes` are tenant-isolated
and PII/secret-free. `SupportIncidentService` redacts titles/summaries/note bodies
(`SupportRedactor::redactText`) and metadata (`SupportRedactor::redact`), requires
a reason code, and audits create/update/note. Status transitions to
resolved/closed stamp `resolved_at`/`closed_at`.

## Support audit model (SUP-R006/R026)

`SupportAuditService::record` writes an append-only `tenant_support_actions` row
(allowed/denied/completed/failed) for every support action and, for mutations,
mirrors a redacted entry to `admin_audit_logs` via `AdminAuditLogger`. Reason
codes are validated against the enumerable `reason_codes` config.

## Redaction model (SUP-R007)

`SupportRedactor` mirrors the Sprint 30/31/32/33/34 redactors: recursive
case-insensitive key redaction (password/token/secret/signature/phone/email/name/
address/nik/card/payload/fingerprint/device_uuid/raw), depth+length caps, plus a
`redactText` pass that strips inline tokens/emails/keys from free text.

## Route matrix

| Method | Route | Controller@method | Mutates? |
|---|---|---|---|
| GET | `/admin/support-ops/tenants` | AdminSupportConsole@tenants | no |
| GET | `/admin/support-ops/tenants/{tenant}/health` | @health | no |
| GET | `/admin/support-ops/tenants/{tenant}/timeline` | @timeline | no |
| GET | `/admin/support-ops/tenants/{tenant}/billing` | @billing | no |
| GET | `/admin/support-ops/tenants/{tenant}/payments` | @payments | no |
| GET | `/admin/support-ops/tenants/{tenant}/entitlements` | @entitlements | no |
| GET | `/admin/support-ops/tenants/{tenant}/onboarding` | @onboarding | no |
| GET | `/admin/support-ops/tenants/{tenant}/android-runtime` | @androidRuntime | no |
| POST | `/admin/support-ops/tenants/{tenant}/devices/{activation}/revoke` | AdminSupportDevice@revoke | Sprint 34 service |
| POST | `/admin/support-ops/tenants/{tenant}/devices/{activation}/reactivate` | @reactivate | governed not-supported |
| GET | `/admin/support-ops/incidents` | AdminSupportIncident@index | no |
| POST | `/admin/support-ops/incidents` | @store | incident |
| GET | `/admin/support-ops/incidents/{incident}` | @show | no |
| PATCH | `/admin/support-ops/incidents/{incident}` | @update | incident |
| POST | `/admin/support-ops/incidents/{incident}/notes` | @addNote | note |
| POST | `/admin/support-ops/tenants/{tenant}/read-only-context/start` | AdminSupportSession@startReadOnlyContext | session |
| POST | `/admin/support-ops/sessions/{session}/end` | @end | session |
| POST | `/admin/support-ops/tenants/{tenant}/impersonation/start` | @startImpersonation | disabled (403) |
| GET | `/admin/support-ops/governance` | AdminSupportConsole@governance | no |

All routes are inside the `admin` + `platform.admin` group. No tenant/public route
exists.

## Command matrix

| Command | Purpose |
|---|---|
| `support-ops:tenant-health` | tenant health overview / brief list |
| `support-ops:timeline` | deterministic diagnostic timeline |
| `support-ops:billing-status` | read-only billing/collection status |
| `support-ops:payment-status` | read-only payment intent/gateway status |
| `support-ops:entitlement-denials` | blocked/denied action explorer |
| `support-ops:sync-failures` | Sprint 34 sync failures/conflicts |
| `support-ops:incident-summary` | incidents by status/severity/category |
| `support-ops:device-action` | dry-run/execute governed device revoke/reactivate |
| `support-ops:governance-audit` | SUP-R001..R030 wiring audit |
| `support-ops:go-no-go` | hard Sprint 35 gate |

## Data model

- `tenant_support_incidents` — incident_number (unique), category, severity,
  status, title_safe, summary_safe, primary_reason_code, related subject, opened/
  resolved/closed timestamps, metadata_json (redacted).
- `tenant_support_incident_notes` — incident + tenant + author, note_type,
  body_safe (redacted), metadata_json.
- `tenant_support_actions` — append-only ledger: action_key, action_type, status,
  reason_code, related subject, support_session_id, metadata_json (redacted).
- `tenant_support_sessions` — read_only_context/impersonation, status, reason_code,
  starts_at/expires_at/ended_at, scope_json/metadata_json (no credentials).

## Dependency graph

```
SupportGoNoGoService ─► SupportGovernanceAuditService
                      └► Artisan command registry (own + Sprint 24–34 gates)
                      └► class_exists(chain services incl. Sprint 34 revoke)
SupportTenantHealthService ─► 5 viewers ─► Sprint 30/31/32/33/34 models
SupportDiagnosticTimelineService ─► all ledgers + SupportRedactor
SupportDeviceOperationsService ─► Sprint 34 DeviceRevocationService + SupportAuditService
Support{Incident,ReadOnlyContext,Impersonation}Service ─► SupportAuditService + SupportRedactor
SupportAuditService ─► AdminAuditLogger + tenant_support_actions
```

## Rollback

Fully additive. To roll back: revert the branch merge (drops the four
`tenant_support_*` tables via their down() migrations, the `SupportOperations`
services, the `support-ops/*` routes/controllers/requests/resources, the
`support-ops:*` commands, and the `config/support_operations_governance.php`
governance) and the `pos_foundation.php`/`PROJECT_RULES.md` Sprint 35 additions.
No prior-sprint table or behaviour is touched, so rollback cannot corrupt billing,
payment, entitlement, onboarding, device or sync state.

## Tests / CI / smoke evidence

- Backend feature tests under `tests/Feature/Sprint35*` cover governance, health,
  timeline, viewers, device flow, incidents, sessions, routes and commands.
- `scripts/sprint35_smoke.sh` — structural + command + governance-gate validation
  on an isolated sqlite file, plus a deterministic incident/note/device-revoke
  probe and no-secret/PII assertions.
- `.github/workflows/sprint35-ci.yml` — backend tests, Android build + unit tests,
  Sprint 24–34 prior gates, `support-ops:governance-audit`, `support-ops:go-no-go`,
  the smoke script, and a SUP-R001..R030 grep across config/foundation/rules/docs.

## Deferred risks

- Governed impersonation remains intentionally unimplemented; if a future sprint
  needs it, it must satisfy the `impersonation_safe` governance signal
  (read-only-only, no raw credentials) before being enabled.
- Governed device reactivation is deferred to the standard activation flow; a
  future support-side reactivation must re-run the Sprint 34 entitlement/device
  limit gate rather than flipping a status.
- Tenant health is computed on demand per request/command; a very large tenant
  set in the `tenants` list endpoint is bounded by a `limit` (max 100) to keep it
  deterministic and cheap.
