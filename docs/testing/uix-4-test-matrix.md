# UIX-4 — Test Matrix

Run: `php artisan test --filter=Uix4` (from `backend/`). 57 tests, part of the
1452-test full suite.

## Authentication — `Uix4OwnerAuthTest`
- login page loads; valid owner login succeeds; wrong password / unknown email →
  same generic error; cashier / platform admin / owner-without-tenant / inactive
  owner rejected; rate limit triggers; no open redirect; authenticated owner
  redirected away from login; logout invalidates session; logout is POST-only;
  guest cannot reach dashboard or business pages; owner can reach console;
  deactivated / role-changed session denied; login audited without password.

## Surface separation — `Uix4OwnerSurfaceSeparationTest`
- owner session cannot reach `/admin`; platform-admin session cannot reach
  `/owner`; admin `web` session does not authenticate owner guard and vice
  versa; Sanctum token alone does not authenticate the owner web surface; public
  visitor blocked on both consoles.

## Tenant context & cross-tenant — `Uix4OwnerTenantContextTest`
- single-membership context resolves to own tenant; foreign outlet/device → 404;
  outlet search cannot leak a foreign tenant; dashboard counts are tenant-scoped;
  owner with removed tenant is denied.

## Lifecycle — `Uix4OwnerLifecycleTest`
- active tenant operational; suspended tenant dashboard shows suspended status;
  suspended outlets page + archived devices page render the restricted view (no
  business data); suspended owner can still view subscription/billing.

## Security — `Uix4OwnerSecurityTest`
- login + logout forms carry CSRF; login page is noindex; failed login does not
  reflect the password; authenticated pages are non-cacheable (`no-store`);
  console pages render no password hashes.

## Console pages — `Uix4OwnerConsolePagesTest`
- dashboard renders real metric groups; outlet list search + pagination; outlet
  detail for own tenant; device list/detail never expose token/fingerprint hash;
  subscription page shows plan + invoices; usage page shows usage-vs-limit;
  operations page renders without infrastructure details.

## Provisioning — `Uix4TenantOwnerProvisionCommandTest`
- creates owner with hashed password; no visible `--password` option; rejects
  unknown tenant; rejects short / common password; idempotent promotion; refuses
  cross-tenant reassignment; refuses platform-admin email.

## Gates
- `scripts/uix4_design_gate.sh` (chains UIX-3/2/1) — views, a11y, tokens,
  truthful states, read-only routes, no secret leakage, rules documented.
- `scripts/verify_application_foundation_rules.sh` — owner surface gated, owner
  provisioning safe, UIX-4 docs present.

## Responsive / accessibility (manual + runtime)
Widths 360–1920: no horizontal overflow, sidebar collapses to off-canvas with
`aria-expanded` toggle, keyboard operable, visible focus, labelled controls,
reduced-motion honored, `noindex`.
