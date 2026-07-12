# UIX-4 — Tenant Owner Web Console Threat Model

Format: Threat → Preventive control → Detective control → Automated test →
Runtime verification.

| Threat | Preventive | Detective | Test | Runtime |
|--------|-----------|-----------|------|---------|
| Brute force / credential stuffing | Per-(email,ip) RateLimiter (5/60s), generic error | `owner.login.failed` warning log (hashed id) | `Uix4OwnerAuthTest::test_login_is_rate_limited` | Observe lockout after repeated fails |
| User enumeration | One generic message + timing-safe hash for unknown email | — | `test_unknown_email_is_rejected_with_same_generic_message` | Identical response for unknown/known |
| Session fixation | `session()->regenerate()` before login | — | `test_tenant_owner_can_login` (auth as owner) | Session id rotates on login |
| Admin↔owner session crossover | Separate `owner`/`web` guards | Force-logout on failed predicate | `Uix4OwnerSurfaceSeparationTest` (all cases) | Admin cannot open `/owner`, owner cannot open `/admin` |
| API token used on web console | Web console uses session guard only | — | `test_sanctum_token_alone_does_not_authenticate_owner_web` | Sanctum token yields redirect |
| CSRF | `@csrf` on login+logout, VerifyCsrfToken web group | — | `Uix4OwnerSecurityTest` CSRF tests | POST without token rejected |
| Open redirect | `redirect()->intended()` only, no user param | — | `test_no_open_redirect_via_request_field` | External redirect ignored |
| Tenant ID tampering | Tenant resolved server-side; no tenant param | — | `Uix4OwnerTenantContextTest` | No way to pass a tenant id |
| Outlet/device IDOR | Scoped `forTenant` query, 404 on foreign id | — | `test_owner_cannot_view_foreign_tenant_outlet/device` | Foreign id → 404 |
| Cross-tenant search leak | Search constrained to tenant scope | — | `test_outlet_search_cannot_leak_foreign_tenant` | B's rows never appear for A |
| Stale/removed membership | Predicate re-checked each request | Force-logout | `test_owner_with_removed_tenant_is_denied`, `test_owner_whose_role_changed_is_denied` | Role/tenant change denies access |
| Suspended-tenant data exposure | Lifecycle gate → restricted view for business pages | — | `Uix4OwnerLifecycleTest` | Suspended owner sees status, not listings |
| Cached cross-tenant response | `no-store, private` on authenticated pages | — | `test_authenticated_console_pages_are_not_cacheable` | Cache-Control header present |
| XSS via tenant/outlet names | Blade `{{ }}` auto-escaping | — | rendering tests | No unescaped output |
| Sensitive device data leak | Only `toSafeArray()` columns rendered; hashes excluded | Design gate greps views for hash keys | `test_device_..._never_expose_token_or_fingerprint_hash` | Hash never in page |
| Audit / log leakage | `AdminAuditLogger::sanitize()`, hashed identifiers | — | `test_successful_login_is_audited_without_password` | Audit rows carry no secret |
| Password leakage via provisioning | Hidden prompt/STDIN, no `--password`, strength check | `tenant.owner.provisioned` (hashed) log | `Uix4TenantOwnerProvisionCommandTest` | No plaintext in history/logs |
| Account hijack via provisioning | Refuse cross-tenant reassignment / platform-admin email | — | `test_refuses_to_reassign_owner_from_another_tenant`, `test_refuses_platform_admin_email` | Command fails safely |
| Public plaintext transport | Encrypted operator channel only; NO-GO on public HTTP | Deployment evidence | — | Console bound to private channel |
