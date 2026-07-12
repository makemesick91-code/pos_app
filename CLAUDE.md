# CLAUDE.md — Aish POS Project Instructions

Aish POS is a multi-tenant Android Point-of-Sale SaaS. The Laravel backend lives in
`backend/`. This file is the top-level index; detailed, enforceable rules live in
`.claude/rules/` and `docs/PROJECT_RULES.md`. When a rule here and a modular rule
appear to conflict, the modular rule in `.claude/rules/` is authoritative.

## Product identity
- Aish POS = white/blue branded, tenant-scoped POS for small merchants, sold as SaaS.
- Backend: Laravel (PHP 8.5) in `backend/`. Build-free Blade for web surfaces (no Node/Vite at deploy).
- Do not add tenant-facing admin web consoles beyond what a sprint authorizes.

## Four-surface architecture
1. Public website — Blade marketing/landing pages, no privileged data.
2. Android / tenant API — Sanctum tokens, tenant-scoped; every business route runs the
   lifecycle → entitlement → usage-limit guard chain.
3. Platform Admin API — `/api/v1/admin/*`, Sanctum + `platform.admin` middleware.
4. Platform Admin browser console — `/admin/*`, session/cookie + `platform.admin.web`
   middleware (delivered by UIX-3).

## Multi-tenant boundary
- Tenant data is always scoped to the authenticated tenant; never read/write across tenants.
- `TenantLifecycleService::resolve()` is the ONLY authoritative tenant status. Do not
  recompute suspension/trial/paid state in controllers or Blade.

## Platform-admin boundary
- Platform admin identity = `users.is_platform_admin` (boolean) AND `is_active`.
  Never a tenant role, never a tenant permission.
- The predicate lives in `App\Http\Middleware\EnsurePlatformAdmin` (API) and
  `EnsurePlatformAdminWeb` (web). Do not reimplement the check inline.

## Source-of-truth rule
- Business truth lives in `App\Services\*` domain services. Billing, entitlement, usage,
  QRIS/payment, onboarding, lifecycle each have a canonical service — reuse it.
- Controllers and Blade views orchestrate and present only; they must not duplicate or
  fork business logic that a service already owns.

## Security baseline
- No production default credentials. No seeded/hardcoded admin passwords in any
  environment that can be reached in production. Secrets come from environment only.
- Never expose secrets, tokens, or raw payment data in responses, logs, or Blade output.
- Redact through `App\Services\Admin\AdminAuditLogger::sanitize()` before logging.

## Testing command index
- Full suite: `php artisan test` (in-memory sqlite), run from `backend/`.
- Targeted: `php artisan test --filter <TestName>`.
- Platform admin tests build users via `User::factory()->platformAdmin()`.
- Design gates: `scripts/uix1_design_gate.sh`, `scripts/uix2_design_gate.sh`,
  `scripts/uix3_design_gate.sh`.
- Foundation rules check: `scripts/verify_application_foundation_rules.sh`.

## Authoritative CI
- The `pull_request` workflows are the authoritative gate. Local runs are advisory.
- A change is not "green" until the PR workflows pass; do not merge on local pass alone.

## Deployment isolation
- Shared VPS, co-tenanted with DaengtisiaMS (asia-dental-lab-v2). Aish is fully isolated:
  php8.5-fpm pool `aish-pos`, nginx site `aish-pos` on port 8080, systemd unit
  `aish-pos-queue-worker`, database `aish_pos_pilot`.
- DaengtisiaMS uses php8.3-fpm and must NEVER be modified. Aish work must never touch
  php8.3, the `daeng` user, or DMS nginx/systemd/DB.
- No HTTPS/domain yet: public plaintext admin exposure is NO-GO. The admin console is
  reachable only over an encrypted operator channel (SSH tunnel / VPN).

## DMS non-regression
- Every deploy must be preceded and followed by a DaengtisiaMS non-regression check.
  If DMS is affected in any way, the change is NO-GO and must be rolled back.

## GO-tag rule
- No GO tag before: successful deploy + runtime verification + DMS non-regression +
  real (non-placeholder) evidence captured.
- Existing GO tags are immutable. The final release commit must be equal across local,
  origin, and the VPS. GO tags are annotated.

## Pointers
- Modular enforceable rules: `.claude/rules/` (00–90).
- Full project rules & rule-set IDs (incl. UIX3-R001..R016): `docs/PROJECT_RULES.md`.
- Architecture/foundation docs: `docs/foundation/` (see
  `docs/foundation/uix-3-platform-admin-control-center.md`).
- Governance synthesis: `docs/governance/application-foundation-rules.md`.
- Deployment runbook: see `docs/` deployment runbook and pilot deployment docs.
