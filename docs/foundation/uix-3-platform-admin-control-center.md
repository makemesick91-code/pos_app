# Aish POS UIX-3 — Platform Admin Login & SaaS Control Center Foundation

Permanent foundation rules for the browser-based Platform Admin portal. These
are enforced by tests (`backend/tests/Feature/Uix3*`), the design gate
(`scripts/uix3_design_gate.sh`), the application-foundation gate
(`scripts/verify_application_foundation_rules.sh`), and CI (`uix3-ci.yml`).
They are additive to UIX-1 (`UIX-R001..R022`) and UIX-2 (`UIX2-R001..R016`).

## Surfaces

Aish POS has four distinct surfaces, each with its own authorization boundary:

1. **Public website** — unauthenticated Blade (Sprint 21 / UIX-2).
2. **Android cashier / tenant API** — Sanctum bearer tokens, tenant-scoped.
3. **Platform Admin API** — `/api/v1/admin/*`, Sanctum + `platform.admin` (Sprint 11).
4. **Platform Admin browser console** — `/admin/*`, session/cookie + `platform.admin.web` (**UIX-3**).

## Rules (UIX3-R001..UIX3-R016)

- **UIX3-R001** — A platform admin is a backend identity (`users.is_platform_admin` AND `is_active`), never a tenant role. Tenant context (tenant_id, role, request input) can never grant platform privilege.
- **UIX3-R002** — The browser console (`/admin/*`) is a distinct surface from the API and the public website. Every non-login console route is guarded by `platform.admin.web` and is deny-by-default; unauthenticated visitors are redirected to login, non-admin sessions are logged out.
- **UIX3-R003** — No production default credentials. Platform admins are provisioned by `php artisan platform:admin-provision`: password via hidden prompt or STDIN (never a visible CLI argument), strength-validated, framework-hashed, and never logged, echoed, or stored in plaintext.
- **UIX3-R004** — Login uses a single generic failure message for every failure mode (no account enumeration), per-(email,ip) rate limiting, session id regeneration on success, timing normalization for unknown accounts, and no user-supplied redirect target (no open redirect).
- **UIX3-R005** — All state-changing admin requests are CSRF-protected (the framework `web` group). Logout is POST-only and invalidates the session.
- **UIX3-R006** — Authenticated console responses are non-cacheable (`Cache-Control: no-store, private`) so shared proxies never retain per-admin data.
- **UIX3-R007** — Control-center metrics reuse existing governed summary services. The console recomputes no business status of its own; any unavailable metric group renders an explicit "unavailable" state and never a fabricated zero.
- **UIX3-R008** — Authoritative tenant lifecycle status comes only from `TenantLifecycleService::resolve()`. It is never recomputed in a controller or view.
- **UIX3-R009** — Tenant list/detail reuse `AdminTenantService` and `SupportTenantHealthService`; their summaries already apply the domain redactors. No password hash, token, secret, session id, webhook secret, or unnecessary PII is ever rendered.
- **UIX3-R010** — UIX-3 is a read-only foundation: there are no tenant mutation routes. A mutation may be added later only with an existing governed service, an explicit policy, idempotency, audit, confirmation UX, and tests — never by duplicating lifecycle/billing/entitlement logic in a controller.
- **UIX3-R011** — Privileged cross-tenant detail views and console login/logout are audited via `AdminAuditLogger` (which sanitizes secret-looking keys). Audit records never store passwords, session ids, remember tokens, CSRF tokens, cookies, or full sensitive payloads. Failed logins are recorded in the app log with a hashed identifier only.
- **UIX3-R012** — Dashboard and list queries are bounded: grouped aggregate counts, pagination capped at 50 rows, and per-tenant resolution limited to the current page. No query fans out across all tenants and no N+1.
- **UIX3-R013** — The console reuses the UIX-1/UIX-2 design tokens (`backend/resources/css/aish-tokens.css`), build-free Blade (no Node/Vite dependency). Markup is semantic, keyboard-usable with visible focus, labels every field, exposes `aria-expanded` on the nav toggle, honors reduced motion, and is responsive across 360–1920 px with no horizontal overflow.
- **UIX3-R014** — Admin pages are `noindex, nofollow` with a same-origin referrer policy.
- **UIX3-R015** — Shared-VPS isolation is preserved; DaengtisiaMS is untouched and non-regressed. While HTTPS/domain is unavailable, the admin portal is reachable only via an encrypted operator channel (SSH tunnel / VPN / private network); public plaintext-HTTP admin usage is **NO-GO** and is stated truthfully in evidence.
- **UIX3-R016** — Authoritative CI is the `pull_request` workflow set; success-by-skipping and fake-green are forbidden. The UIX-3 GO tag is created only after merge, deploy, runtime verification, and DaengtisiaMS non-regression evidence. Existing GO tags are immutable.
