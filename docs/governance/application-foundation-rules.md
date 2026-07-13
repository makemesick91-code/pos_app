# Application Foundation Rules — Aish POS

A human-readable synthesis of the enforceable governance rules for Aish POS, the
multi-tenant Android Point-of-Sale SaaS (Laravel backend in `backend/`, PHP 8.5,
build-free Blade). Each section links to the modular rule that governs in detail. When
this document and a modular rule appear to differ, the modular rule in `.claude/rules/`
is authoritative.

Modular rules (`.claude/rules/`):
00 project-foundation · 10 architecture-and-source-of-truth ·
20 multi-tenancy-and-authorization · 30 authentication-session-and-security ·
40 uiux-accessibility-and-responsive · 50 data-privacy-audit-and-redaction ·
60 testing-quality-and-performance · 70 ci-runtime-control ·
80 deployment-backup-and-rollback · 90 release-evidence-and-go-tag.

Presence of these files is verified by `scripts/verify_application_foundation_rules.sh`.

## Architecture boundaries (rules 00, 10)
Aish POS exposes four surfaces: the public Blade website, the Android/tenant API
(Sanctum, tenant-scoped), the Platform Admin API (`/api/v1/admin/*`, Sanctum +
`platform.admin`), and the Platform Admin browser console (`/admin/*`, session/cookie +
`platform.admin.web`, delivered by UIX-3). Business truth lives in `App\Services\*`
domain services; `TenantLifecycleService::resolve()` is the single authoritative tenant
status, and billing, entitlement, usage, payment-gateway/QRIS, and onboarding each have a
canonical service. Controllers and Blade orchestrate and present — they never duplicate or
fork business logic. Business routes run the guard chain
`tenant.lifecycle → tenant.entitled → usage/limit`.

## Tenancy & authorization (rule 20)
Tenant data is always scoped to the authenticated tenant; cross-tenant access is a
platform-admin concern handled through admin services. Platform-admin identity is
`users.is_platform_admin` AND `is_active` — never a tenant role — enforced by
`EnsurePlatformAdmin` (API) and `EnsurePlatformAdminWeb` (web). Admin surfaces are
read-only by default; mutations are explicit, authorized, and audited. Manual suspension
wins over paid billing and is never lifted by a payment; impersonation is disabled.

## Security (rule 30)
There is no production default credential and no secret exposure. Secrets come from the
environment only. Sanctum secures the APIs; the `/admin/*` console uses CSRF-protected
server sessions behind `platform.admin.web`, with the platform-admin predicate re-checked
per request. No HTTPS/domain exists yet, so public plaintext admin exposure is NO-GO — the
console is reachable only over an encrypted operator channel (SSH tunnel/VPN) on an
IP-restricted port 8080. Device tokens are stored only as sha256 hashes.

## UI truthfulness & accessibility (rule 40)
Color/spacing/typography come from `backend/resources/css/aish-tokens.css`
(white/blue foundation, gold as a limited accent, zero hardcoded hex). The UI must reflect
real service state — no fabricated success, metrics, or placeholder data on authoritative
surfaces. Pages meet WCAG AA contrast, are keyboard-operable, and are responsive without
horizontal overflow. Enforced by `scripts/uix1/2/3_design_gate.sh`; UIX-3 adds rule set
UIX3-R001..R016 (see `docs/foundation/uix-3-platform-admin-control-center.md` and
`docs/PROJECT_RULES.md`).

## Data privacy, audit & redaction (rule 50)
Privileged actions are logged via `App\Services\Admin\AdminAuditLogger::log()` into
`admin_audit_logs`, with payloads redacted through `sanitize()` (password/secret/token and
similar). Admin/observability consoles read canonical ledgers without mutating them; a
diagnostic never marks an invoice paid, unlocks entitlement, reactivates a tenant, or
bypasses suspension. Ledgers stay append-only; corrections are governed signed-delta
repairs.

## Testing, quality & performance (rule 60)
Run `php artisan test` (in-memory sqlite) from `backend/`; iterate with `--filter`;
platform-admin tests use `User::factory()->platformAdmin()`. New services, middleware, and
guards ship with allow/deny tests covering tenant isolation and the admin gate. Avoid N+1
and unbounded scans in request paths; heavy work runs on the queue worker.

## CI (rule 70)
The `pull_request` workflows are authoritative — local passes are advisory. CI runs on
PHP 8.5; required checks include the backend suite, the design gates, and
`verify_application_foundation_rules.sh`. Never weaken a guardrail or lower the PHP version
to go green. Merges reach `main` only through reviewed PRs with green CI.

## Deployment (rule 80)
The Aish stack is isolated on a shared VPS: php8.5-fpm pool `aish-pos`, nginx site
`aish-pos` on port 8080, systemd `aish-pos-queue-worker`, database `aish_pos_pilot`.
DaengtisiaMS (php8.3-fpm) must never be modified; every deploy is bracketed by a DMS
non-regression check, and any DMS impact is NO-GO with immediate rollback. Back up
`aish_pos_pilot` and record the current release commit before migrating; deploy only when
rollback is possible.

## Release governance (rule 90)
No GO tag before green authoritative CI, successful deploy, runtime verification, DMS
non-regression, and real non-placeholder evidence. The final release commit must be equal
across local, origin, and the VPS. GO tags are annotated and immutable — existing tags are
never moved or overwritten. Absence of proof is NO-GO.

## Tenant Owner Web Console (UIX-4)
The fifth surface, `/owner/*`, is a session/cookie console for a tenant owner over the
dedicated `owner` guard, gated by `tenant.owner.web`. Owner identity is
`is_active` AND `role = tenant_owner` AND a resolvable tenant — never a platform
capability, and separate from the platform-admin `web` guard. Tenant context is resolved
server-side from the owner's own record (`OwnerContextResolver`), never from request
input; every query is explicitly tenant-scoped. The console is read-only first, reuses the
canonical domain services as the source of truth, renders truthful unavailable states, and
never exposes device token/fingerprint hashes. Full rule set UIX4-R001..R022 lives in
`docs/foundation/uix-4-tenant-owner-web-console.md` and
`docs/governance/tenant-owner-web-console-foundation.md`.

## Subscription, Billing & Invoice Console (UIX-5)
A read-only billing console added to two surfaces: Tenant Owner Billing Center
(`/owner/billing/*`, `owner` guard) and Platform Admin Billing Operations
(`/admin/billing/*`, `platform.admin.web`). It is presentation over the canonical
billing domain (Sprint 30/31 `tenant_billing_*` + `App\Services\Billing\*` /
`App\Services\PaymentGateway\*`), never a second billing engine: `BillingConsoleReadService`
reads canonical columns and calls canonical methods, never recomputing totals, paid,
outstanding, lifecycle, or settlement. Money is whole-rupiah integer (no floats, no
`/100`), formatted only via `<x-rupiah>`, with a truthful "Tidak tersedia" for unavailable
values. Owner billing is tenant-scoped deny-by-default (foreign/unknown invoice → 404);
invoice documents are authenticated, non-path-traversable, with no public URL; QRIS/settlement
state stays distinct from invoice collection state. Read-only: no mutation routes. Full rule
set UIX5-R001..R028 lives in `docs/foundation/uix-5-subscription-billing-invoice-console.md`,
`docs/governance/subscription-billing-invoice-foundation.md`, and
`.claude/rules/35-subscription-billing-invoice-integrity.md`.

## Android Cashier Experience (UIX-7)
A remediation of the Android Cashier app (`com.aishtech.poslite`, native Views/XML +
Retrofit/OkHttp + Room + WorkManager) cashier experience over the existing Android and
backend domain services — not a feature expansion and never a second pricing, payment,
QRIS, settlement, or sync engine. The app is a distinct tenant/device-scoped surface
authenticated by Sanctum API tokens; it never inherits Platform Admin or Tenant Owner web
authorization, and UI/ViewModels present canonical state only. Offline sales are durably
persisted before a success state is shown; an interrupted in-flight sync is recoverable
rather than stranded and silently lost; retries are idempotent on the device
`clientReference` (no duplicate server transactions); a row is SYNCED only on canonical
server acknowledgement; and checkout carries a ViewModel-level double-submit guard. Money
is a canonical whole-rupiah integer (`core/money/RupiahMoney`, `Long`) with no unsafe float
math in new/changed cashier code, formatted only through the single canonical formatter,
with "Tidak tersedia" for unknown values; QRIS creation is never presented as paid/settled
and QRIS is online-only. Security: `allowBackup=false`, cleartext denied by default
(release/pilot → `https://aishpos.online`), `Authorization` redacted from debug-only logs,
and no credentials/tokens/PII in logs, screenshots, or test artifacts. Because this build
environment has no Android SDK/emulator/device, CI (JDK 21) is the authoritative build/test
gate; on-device authenticated runtime verification against `aishpos.online` and the GO tag
are operator-performed and deferred until real device evidence is captured — never
fabricated. Full rule set UIX7-R001..R044 lives in
`docs/foundation/uix-7-android-cashier-experience-remediation.md` and
`.claude/rules/55-android-cashier-experience.md`.
