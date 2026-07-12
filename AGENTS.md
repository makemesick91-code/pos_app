# AGENTS.md — Aish POS

Aish POS is a multi-tenant Android Point-of-Sale SaaS with a Laravel (PHP 8.5)
backend in `backend/`. This file is the concise agent index; the enforceable,
authoritative detail lives in `CLAUDE.md`, `.claude/rules/` (00–90, plus 25 and
35), and `docs/PROJECT_RULES.md`. When guidance here and a modular rule conflict,
the modular rule wins.

## Repository structure
- `backend/` — Laravel app (routes, controllers, services, models, migrations,
  Blade views, tests). Build-free Blade for web (no Node/Vite at deploy).
- `.claude/rules/` — modular enforceable rules. `docs/` — foundation, governance,
  deployment runbooks, evidence. `scripts/` — design/foundation gates + deploy.

## Canonical commands
- Test suite: `cd backend && php artisan test` (in-memory sqlite).
- Targeted: `php artisan test --filter <Name>`.
- Gates: `scripts/verify_application_foundation_rules.sh`,
  `scripts/uix{1,2,3,4,5}_design_gate.sh`.
- Authoritative CI = the `pull_request` GitHub workflows, not local runs.

## Surfaces
1. Public marketing website (Blade, no privileged data).
2. Android / tenant API (Sanctum, tenant-scoped).
3. Platform Admin API (`/api/v1/admin/*`, `platform.admin`).
4. Platform Admin browser console (`/admin/*`, `platform.admin.web`) — incl. UIX-5
   Billing Operations (`/admin/billing/*`).
5. Tenant Owner browser console (`/owner/*`, `owner` guard, `tenant.owner.web`) —
   incl. UIX-5 Billing Center (`/owner/billing/*`).

## Boundaries & source of truth
- Tenant data is always scoped to the authenticated tenant; never cross-tenant.
  `TenantLifecycleService::resolve()` is the only authoritative tenant status.
- Business truth lives in `App\Services\*`; controllers and Blade orchestrate and
  present only. Do not fork billing/entitlement/usage/QRIS/settlement logic.
- Platform admin identity = `is_platform_admin` AND `is_active` (never a tenant
  role). Owner identity = `is_active` AND `role = tenant_owner` AND a tenant.

## Billing/financial rules (UIX-5)
- Money is whole-rupiah integer — never a float, never `/100` cents. Format only
  through `<x-rupiah>`; a null amount is "Tidak tersedia", never a fake zero.
- The billing console is read-only: no direct model mutation of invoice/payment/
  subscription/settlement state, no mutation routes. Invoice totals/paid/outstanding
  come from canonical methods, never recomputed in views.
- Invoice downloads are authenticated, tenant/surface-scoped, non-path-traversable;
  no public invoice URL. Distinct QRIS/settlement vs collection semantics.

## Security & release
- No production default credentials; secrets from environment only; redact via
  `AdminAuditLogger::sanitize()`. Public plaintext-HTTP admin/billing exposure = NO-GO.
- Full test suite + authoritative CI must be green before merge. Every deploy is
  bracketed by a DaengtisiaMS non-regression check (rule 80). GO tags are annotated,
  immutable, and require observed evidence + local/origin/VPS exact match (rule 90).

## Pointers
- `CLAUDE.md`, `.claude/rules/` (esp. 25 owner console, 35 billing console),
  `docs/PROJECT_RULES.md`, `docs/foundation/`, `docs/governance/`,
  `docs/deployment/`.
