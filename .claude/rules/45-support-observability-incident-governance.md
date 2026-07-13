# 45 — Support, Observability & Incident Console Governance (UIX-6)

The `/admin/support/*`, `/admin/observability`, `/admin/incidents/*` (Platform
Admin) and `/owner/support/*` (Tenant Owner) surfaces present support health,
operational observability, alerts, and incident state. They are **read-only
presentation over the canonical Sprint 35 (SupportOperations) and Sprint 36
(Observability) domains** — never a second support/alert/incident/health engine.

## Source of truth & no duplication
- UIX6-R001 — Support, observability, alerting, health, and incident services
  remain canonical sources of truth. The console reuses
  `App\Services\SupportOperations\*` and `App\Services\Observability\*`.
- UIX6-R002 — Controllers, view models, and Blade templates must not duplicate
  support, health, alert, or incident logic. `App\Services\SupportConsole\*`
  (`SupportConsoleReadService`, `ObservabilityConsoleReadService`,
  `IncidentConsoleReadService`, `OwnerSupportReadService`) are read adapters:
  they orchestrate canonical methods and shape output; they never recompute.

## Authorization & tenancy
- UIX6-R003 — Platform support and incident routes require dedicated
  platform-admin web authorization (`platform.admin.web`), enforced on the route
  group, never inline.
- UIX6-R004 — Tenant Owner operational/support access is always tenant-scoped and
  deny-by-default; the tenant comes from `OwnerContext` (server-resolved), never
  from request input.
- UIX6-R005 — Tenant Owner access never grants platform-global observability or
  incident visibility.
- UIX6-R006 — Platform Admin access never grants Tenant Owner membership.
- UIX6-R007 — Raw tenant IDs from client input are never trusted.
- UIX6-R008 — Incident and support route-model binding must enforce surface and
  tenant/platform authorization; a foreign/unknown id returns 404. Owner
  incidents are never resolved by implicit route-model binding.

## Privacy, redaction & safe diagnostics
- UIX6-R009 — Raw logs, stack traces, environment values, credentials, tokens,
  cookies, and private headers must never be rendered in browser consoles.
- UIX6-R010 — Tenant Owner views must not expose hosts, internal IPs, worker
  names, database roles, infrastructure topology, or platform-global identifiers.
- UIX6-R019 — Audit records must redact credentials, tokens, raw payloads, and
  unnecessary PII (reads reuse `AdminAuditLogger` sanitization and the domain
  `SupportRedactor`/`ObservabilityRedactor`).

## Observability integrity (truthful health)
- UIX6-R011 — Unknown health is not healthy, and stale health must be represented
  explicitly. A component whose evidence is missing or stale is presented as
  `unknown`, never `healthy` (the canonical aggregate that folds
  `no_runs_recorded` / a missing subsystem into "healthy" is corrected in
  presentation).
- UIX6-R012 — Metrics and operational statuses must include truthful freshness
  semantics when available (snapshot `checked_at` age / stale indicator).
- UIX6-R013 — Unsupported values render as unavailable ("Tidak tersedia"), never
  as fabricated success, zero, or healthy status.

## Incident semantics & governance
- UIX6-R014 — Incident severity, status, impact, acknowledgement, mitigation, and
  resolution semantics come from canonical domain services/models
  (`ProductionIncident`, `TenantSupportIncident`).
- UIX6-R015 — Direct incident, alert, tenant, device, sync, payment, or
  settlement state updates from UI controllers are forbidden.
- UIX6-R016 — Support and incident consoles are read-only first unless a governed
  mutation service already exists. UIX-6 ships read-only; incident transitions
  are deferred.
- UIX6-R017 — Incident mutations require explicit authorization, confirmation,
  idempotency, audit, regression tests, and compensation/rollback semantics.
- UIX6-R018 — Incident timelines and historical evidence are append-only or
  modified only through canonical correction mechanisms.

## Cache, polling & performance
- UIX6-R020 — Observability cache keys include surface, identity, tenant,
  metric/query scope, and freshness window.
- UIX6-R021 — Tenant A observability/support data must never be served to Tenant
  B or to another identity through cache reuse.
- UIX6-R022 — Lists and timelines must be paginated or strictly bounded.
- UIX6-R023 — Polling intervals must be bounded and must not create excessive
  platform load.
- UIX6-R024 — Real-time claims are forbidden unless the data path is genuinely
  real-time.
- UIX6-R025 — Search, filters, and sorting fields must be explicitly validated
  and whitelisted.
- UIX6-R026 — Support exports or evidence downloads require authenticated and
  authorized delivery with redaction.

## Release gates
- UIX6-R027 — Cross-tenant support, incident detail, timeline, export, and
  cache-isolation tests are mandatory release blockers.
- UIX6-R028 — Raw-log, stack-trace, secret, and PII leakage tests are mandatory
  release blockers.
- UIX6-R029 — Authenticated production runtime verification is mandatory;
  CI-only browser behavior is insufficient for UIX-6 GO.
- UIX6-R030 — HTTPS and HTTP-to-HTTPS redirect must remain active for
  authenticated support and incident consoles.
- UIX6-R031 — Production Artisan cache operations must preserve the PHP-FPM
  runtime ownership of `storage/framework` and `bootstrap/cache`.
- UIX6-R032 — Shared-VPS deployment must not change or regress DaengtisiaMS.
- UIX6-R033 — GO requires observed evidence, authoritative CI success,
  local/origin/VPS exact match, runtime verification, and immutable previous tags.
