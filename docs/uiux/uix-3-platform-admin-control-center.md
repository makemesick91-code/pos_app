# UIX-3 — Platform Admin Control Center (UI Spec & Coverage)

Build-free Blade UI for the platform-admin SaaS Control Center. Views extend
`resources/views/admin/layout.blade.php`, which inlines
`backend/resources/css/aish-tokens.css` (design tokens) at render time — no build
step, no external asset. Copy is Indonesian. The admin surface is `noindex,
nofollow` with `referrer: same-origin`.

## Screens

| Screen | View | Route |
| --- | --- | --- |
| Login | `resources/views/admin/login.blade.php` | `GET /admin/login` (standalone page, outside the console shell) |
| Dashboard | `resources/views/admin/dashboard.blade.php` | `GET /admin` |
| Tenant list | `resources/views/admin/tenants/index.blade.php` | `GET /admin/tenants` |
| Tenant detail | `resources/views/admin/tenants/show.blade.php` | `GET /admin/tenants/{tenant}` |

- **Login** is a standalone page so an unauthenticated visitor never sees console
  chrome (sidebar/nav). Includes a labelled email + password field and a
  password show/hide toggle.
- **Dashboard** renders the governed metric groups from
  `ControlCenterMetricsService::overview()`; any unavailable group shows an
  explicit "unavailable" state, never a fabricated `0`.
- **Tenant list** is a paginated table with search + status filter; each row
  shows the authoritative lifecycle status.
- **Tenant detail** renders the redacted fused health overview
  (billing/payment/entitlement/onboarding/android), lifecycle, and subscription
  summary.

## Components reused (from `aish-tokens.css` / design foundation)

| Component / class | Usage |
| --- | --- |
| `.aish-btn-primary` | Primary actions (login submit, primary CTA) |
| `.aish-badge--*` | Status badges (lifecycle / health / billing states) |
| `.aish-num` | Numeric metric values on the dashboard |
| Design tokens (`--aish-*`) | Colors, spacing, typography, brand — e.g. `--aish-font`, `--aish-text-primary`, `--aish-bg-default`, `--aish-action-primary`, `--aish-brand-dark`, `--aish-space-*` |

The layout uses only tokenized values (no hardcoded hex in content styling) —
consistent with the UIX-1 design-foundation lock.

## Accessibility checklist

- [x] Skip link ("skip to content") as the first focusable element
- [x] `aria-expanded` on the collapsible navigation control
- [x] `aria-current` on the active nav item
- [x] Visible focus ring (`:focus-visible` outline, not suppressed)
- [x] Labelled form fields (email, password)
- [x] Password show/hide toggle exposes state via `aria-pressed`
- [x] Reduced-motion respected (`prefers-reduced-motion`)
- [x] Responsive with `overflow-x` guarded on the body; wide tables wrapped in an
      `overflow-x: auto` container
- [x] `lang="id"` document, Indonesian copy
- [x] `noindex, nofollow` + `referrer: same-origin` meta

## Responsive viewport list

Layout verified to have no horizontal body overflow and to keep wide tables
scrollable within their own container at:

`360, 390, 412, 768, 1024, 1280, 1440, 1920` (px width).

The console shell collapses to a single column on narrow widths; tables remain in
an `overflow-x: auto` region so the page body itself never scrolls horizontally.

## UIX-3 rule reference (UIX3-R001 .. UIX3-R016)

| Rule | Intent |
| --- | --- |
| UIX3-R001 | Admin console is a distinct surface (session/cookie), separate from the Sanctum API and the public website |
| UIX3-R002 | All console routes gated by `platform.admin.web` (`is_active && isPlatformAdmin`), deny-by-default |
| UIX3-R003 | Login page is standalone; unauthenticated users never see console chrome |
| UIX3-R004 | Authorization is backend-enforced only — no request input/hidden field grants admin |
| UIX3-R005 | Read-only foundation: NO tenant mutation routes exist |
| UIX3-R006 | Dashboard reuses governed summary services; no business logic duplicated in the UI |
| UIX3-R007 | Unavailable metric source renders an explicit "unavailable" state, never a fabricated 0 |
| UIX3-R008 | Lifecycle status is authoritative (`TenantLifecycleService`), never recomputed in the view |
| UIX3-R009 | Tenant detail data is already redacted at the source service |
| UIX3-R010 | Cross-tenant view is audited (`ACTION_TENANT_VIEWED`) |
| UIX3-R011 | Build-free Blade extending the shared layout; tokens inlined from `aish-tokens.css` |
| UIX3-R012 | No hardcoded hex in content styling; use design tokens |
| UIX3-R013 | Accessibility: skip link, aria-expanded/current, visible focus, labelled fields, aria-pressed toggle, reduced-motion |
| UIX3-R014 | Responsive across the listed viewports; body never scrolls horizontally; wide tables in `overflow-x:auto` |
| UIX3-R015 | Indonesian copy; `noindex/nofollow` + same-origin referrer on the admin surface |
| UIX3-R016 | Admin surface reachable only via the encrypted operator channel while no HTTPS/domain; public plaintext HTTP = NO-GO |

## Coverage note

Server-rendered contract (routes, headers, redaction, markup attributes) is
covered by the UIX-3 feature suite (`Uix3AdminAuthTest`, `Uix3ControlCenterTest`,
`Uix3AdminSecurityTest`, `Uix3PlatformAdminProvisionCommandTest` — 41 tests).
Purely visual and per-viewport behaviour is confirmed by runtime verification at
the deploy window (see `docs/testing/uix-3-test-matrix.md`).
