# 00 — Project Foundation

Foundational facts and non-negotiables for Aish POS. Read before touching any surface.

## What this project is
- Aish POS: multi-tenant Android Point-of-Sale SaaS with a Laravel backend in `backend/`.
- PHP 8.5 runtime. Build-free Blade for web (no Node/Vite at deploy time).
- White/blue visual foundation with gold as a limited accent; tokens in
  `backend/resources/css/aish-tokens.css`.

## The four surfaces
- Public website — Blade marketing/landing; no privileged or tenant data.
- Android / tenant API — Sanctum, tenant-scoped business operations.
- Platform Admin API — `/api/v1/admin/*`, Sanctum + `platform.admin` middleware.
- Platform Admin browser console — `/admin/*`, session/cookie + `platform.admin.web`
  middleware (UIX-3).

## Ground rules
- Do the authorized scope: no unauthorized surfaces, no tenant-facing admin consoles.
- Business truth belongs in `App\Services\*`; controllers and Blade orchestrate/present.
- No production default credentials and no secret exposure (see rule 30).
- Every change must keep DaengtisiaMS (co-tenant on the shared VPS) untouched (see rule 80).

## Where the rules live
- Modular rules: `.claude/rules/00`–`90` (this directory).
- Rule-set IDs and full narrative: `docs/PROJECT_RULES.md`, `docs/foundation/`.
- Governance synthesis: `docs/governance/application-foundation-rules.md`.

## Verification entry points
- `php artisan test` (from `backend/`) — in-memory sqlite suite.
- `scripts/uix1_design_gate.sh`, `scripts/uix2_design_gate.sh`, `scripts/uix3_design_gate.sh`.
- `scripts/verify_application_foundation_rules.sh` — checks these rule files exist.
- Authoritative gate = `pull_request` CI workflows, not local runs.
