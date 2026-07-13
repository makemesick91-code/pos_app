# UIX-6 — Support, Observability & Incident Console (Foundation)

Aish POS UIX-6 adds **read-only** browser presentation for support, operational
observability, and incident visibility on the two authenticated web surfaces:

- **Platform Admin** (`platform.admin.web`): `/admin/support`,
  `/admin/support/tenants`, `/admin/support/tenants/{tenant}`,
  `/admin/observability`, `/admin/incidents`, `/admin/incidents/{incident}`.
- **Tenant Owner** (`owner` guard, `tenant.owner.web`): `/owner/support`,
  `/owner/support/incidents/{incident}` — strictly tenant-scoped.

It is a presentation layer over the canonical Sprint 35 SupportOperations and
Sprint 36 Observability domains. It introduces **no** new support, alert,
incident, or health engine, and **no** mutation route (read-only first).

## Architecture

```
Route (GET only)
  → platform.admin.web  |  tenant.owner.web (owner guard)
  → Admin controller ($request->user())  |  OwnerController ($this->context())
  → App\Services\SupportConsole\* read adapter
  → EXISTING canonical services (SupportOperations / Observability / models)
  → Blade view (admin.* / owner.*) reusing aish-tokens.css + <x-rupiah> + partials
```

Read adapters (all under `App\Services\SupportConsole\`):

| Adapter | Surface | Reuses (never recomputes) |
|---|---|---|
| `SupportConsoleReadService` | `/admin/support/*` | `SupportTenantHealthService`, `SupportDiagnosticTimelineService`, `Support*ViewerService`, `TenantRuntimeProbeService`, `ObservabilityMetricsService` |
| `ObservabilityConsoleReadService` | `/admin/observability` | `ObservabilityHealthService`, `Infrastructure/Queue/Scheduler/FailedJob/Metrics` services, anomaly/alert **read** |
| `IncidentConsoleReadService` | `/admin/incidents`, `/owner/support/incidents` | `ProductionIncident`, `TenantSupportIncident`, `SupportRedactor` |
| `OwnerSupportReadService` | `/owner/support` | tenant-scoped health + `SupportAndroidRuntimeViewerService` + tenant incidents |

## Truthful health / freshness

The canonical `ObservabilityHealthService::overview()` intentionally folds a
scheduler that never ran (`no_runs_recorded`) and a missing subsystem into an
aggregate "healthy". UIX-6 does **not** paint that green: the observability
presenter (`ObservabilityConsoleReadService::presentHealth()`) assigns each
component a `display_status` that becomes `unknown` when the evidence is absent,
and surfaces the persisted `ObservabilityHealthSnapshot.checked_at` as the
freshness `as_of` with an explicit stale/missing indicator (UIX6-R011/R012/R013).

## Read-only / mutation scope

UIX-6 ships read-only (UIX6-R016). Incident transitions (acknowledge / mitigate /
resolve / reopen / note) and anomaly/alert actions remain in their canonical
governed services and are **not** exposed on the browser console. Any future
browser mutation must satisfy authorization, confirmation, idempotency, audit,
regression tests, and compensation semantics (UIX6-R017).

There is no canonical tenant-facing support-request/ticket service in the domain,
so owner request creation is deliberately not offered; the owner support page
shows a truthful "no self-service channel" state rather than a fake ticketing
engine (prompt §20).

## Rule set (UIX6-R001 … UIX6-R033)

The enforceable rules live in `.claude/rules/45-support-observability-incident-governance.md`
and are registered in `docs/PROJECT_RULES.md`. Summary:

- UIX6-R001 — Canonical support/observability/alert/health/incident services remain source of truth.
- UIX6-R002 — No duplication of support/health/alert/incident logic in controllers/views; `SupportConsole\*` are read adapters.
- UIX6-R003 — Platform support/incident routes require `platform.admin.web`.
- UIX6-R004 — Owner support access is tenant-scoped, deny-by-default, from `OwnerContext`.
- UIX6-R005 — Owner never gains platform-global observability/incident visibility.
- UIX6-R006 — Platform Admin never gains Tenant Owner membership.
- UIX6-R007 — Client-supplied raw tenant IDs are never trusted.
- UIX6-R008 — Incident/support binding enforces surface + tenant/platform scope; foreign id → 404; owner never uses implicit route-model binding.
- UIX6-R009 — No raw logs, stack traces, env values, credentials, tokens, cookies, or private headers in the browser console.
- UIX6-R010 — Owner views expose no hosts, internal IPs, worker names, DB roles, topology, or platform-global identifiers.
- UIX6-R011 — Unknown health is not healthy; stale health is represented explicitly.
- UIX6-R012 — Statuses include truthful freshness semantics when available.
- UIX6-R013 — Unsupported values render as "Tidak tersedia", never fabricated success/zero/healthy.
- UIX6-R014 — Incident severity/status/impact/lifecycle come from canonical services.
- UIX6-R015 — No direct incident/alert/tenant/device/sync/payment/settlement updates from UI controllers.
- UIX6-R016 — Read-only first unless a governed mutation service exists; UIX-6 ships read-only.
- UIX6-R017 — Any incident mutation requires authz, confirmation, idempotency, audit, tests, compensation.
- UIX6-R018 — Incident timelines/evidence append-only or governed correction only.
- UIX6-R019 — Audit redacts credentials, tokens, raw payloads, and unnecessary PII.
- UIX6-R020 — Observability cache keys include surface, identity, tenant, query scope, freshness window.
- UIX6-R021 — Tenant A data never served to Tenant B or another identity via cache reuse.
- UIX6-R022 — Lists and timelines are paginated or strictly bounded.
- UIX6-R023 — Polling intervals are bounded; no excessive load.
- UIX6-R024 — No real-time claims unless the data path is genuinely real-time.
- UIX6-R025 — Search/filter/sort fields are explicitly validated and whitelisted.
- UIX6-R026 — Support exports/evidence downloads are authenticated, authorized, redacted.
- UIX6-R027 — Cross-tenant support/incident/timeline/export/cache-isolation tests are release blockers.
- UIX6-R028 — Raw-log/stack-trace/secret/PII leakage tests are release blockers.
- UIX6-R029 — Authenticated production runtime verification is mandatory for GO.
- UIX6-R030 — HTTPS + HTTP→HTTPS redirect remain active for the authenticated consoles.
- UIX6-R031 — Production Artisan cache ops preserve PHP-FPM ownership of `storage/framework` + `bootstrap/cache`.
- UIX6-R032 — Shared-VPS deployment must not change/regress DaengtisiaMS.
- UIX6-R033 — GO requires observed evidence, authoritative CI, local/origin/VPS exact match, runtime verification, immutable prior tags.

## Verification

- Targeted: `php artisan test --filter Uix6`.
- Design gate: `scripts/uix6_design_gate.sh` (chains UIX-1..UIX-5).
- Foundation gate: `scripts/verify_application_foundation_rules.sh` (UIX-6 block).
- Authoritative CI: `.github/workflows/uix6-ci.yml` (`pull_request`).
