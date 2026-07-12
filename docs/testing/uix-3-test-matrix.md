# UIX-3 — Test Matrix

Maps UIX-3 requirements to automated tests and, where a check is inherently
visual/network-level, to runtime verification during the deploy window.

## Test suites and counts

| Suite (`backend/tests/Feature/`) | Focus | Tests |
| --- | --- | --- |
| `Uix3AdminAuthTest` | Login/logout, throttle, enumeration, session, audit | 17 |
| `Uix3ControlCenterTest` | Dashboard assembly, tenant list, tenant detail, redaction | 11 |
| `Uix3PlatformAdminProvisionCommandTest` | Secure admin provisioning command | 8 |
| `Uix3AdminSecurityTest` | CSRF, open-redirect, cache headers, non-admin denial | 5 |
| **UIX-3 total** | | **41** |

Full backend suite: **1395 tests / 39330 assertions passing**.
Run with:

```bash
php artisan test
```

Platform-admin fixtures are created via `User::factory()->platformAdmin()`.

## Requirements -> tests

### Authentication
| Requirement | Covered by |
| --- | --- |
| Valid platform admin can log in (session established) | `Uix3AdminAuthTest` |
| Session id regenerated on login (fixation defence) | `Uix3AdminAuthTest` |
| Logout invalidates session + regenerates token, POST-only | `Uix3AdminAuthTest` |
| Per-`(email,ip)` rate limit engages after 5 attempts | `Uix3AdminAuthTest` |
| Generic single failure message (no enumeration) | `Uix3AdminAuthTest`, `Uix3AdminSecurityTest` |
| Timing normalized for unknown email (bcrypt on `TIMING_SAFE_HASH`) | `Uix3AdminAuthTest` |
| Failed logins logged with `sha256(email)` only, never password | `Uix3AdminAuthTest` |
| Login/logout audited via `AdminAuditLogger` | `Uix3AdminAuthTest` |

### Authorization
| Requirement | Covered by |
| --- | --- |
| Guest hitting a console route is redirected to login (deny by default) | `Uix3AdminSecurityTest`, `Uix3ControlCenterTest` |
| Non-admin authenticated session is force-logged-out at middleware | `Uix3AdminSecurityTest`, `Uix3AdminAuthTest` |
| Deactivated admin (`is_active=false`) denied | `Uix3AdminAuthTest` |
| Predicate checked before `Auth::login` (no non-admin session created) | `Uix3AdminAuthTest` |
| Authenticated responses carry `Cache-Control: no-store, private` | `Uix3AdminSecurityTest` |

### Tenant user cannot become platform admin
| Requirement | Covered by |
| --- | --- |
| Valid tenant (non-admin) user cannot obtain a console session | `Uix3AdminAuthTest`, `Uix3AdminSecurityTest` |
| No request input / hidden field grants admin | `Uix3AdminSecurityTest`, `Uix3ControlCenterTest` |

### Tenant isolation / cross-tenant read
| Requirement | Covered by |
| --- | --- |
| Tenant detail data is redacted (from `SupportTenantHealthService`) | `Uix3ControlCenterTest` |
| Cross-tenant view audited as `ACTION_TENANT_VIEWED` | `Uix3ControlCenterTest` |
| Whitelisted sort/status/per_page params (no query injection) | `Uix3ControlCenterTest` |

### Dashboard
| Requirement | Covered by |
| --- | --- |
| `overview()` assembles all metric groups from governed services | `Uix3ControlCenterTest` |
| Unavailable source degrades to `{available:false}`, never a fabricated 0 | `Uix3ControlCenterTest` |
| No N+1 (bounded grouped counts) | `Uix3ControlCenterTest` |

### Tenant list / detail
| Requirement | Covered by |
| --- | --- |
| Paginated list via `AdminTenantService::paginate` | `Uix3ControlCenterTest` |
| Authoritative lifecycle resolved per row (never recomputed inline) | `Uix3ControlCenterTest` |
| Detail fuses billing/payment/entitlement/onboarding/android (redacted) | `Uix3ControlCenterTest` |

### Security
| Requirement | Covered by |
| --- | --- |
| CSRF enforced on POST login/logout | `Uix3AdminSecurityTest` |
| No open redirect (`intended()` only) | `Uix3AdminSecurityTest` |
| noindex/nofollow + same-origin referrer, no-store cache headers | `Uix3AdminSecurityTest` |

### Provisioning
| Requirement | Covered by |
| --- | --- |
| Password never accepted as a visible CLI arg (prompt or STDIN) | `Uix3PlatformAdminProvisionCommandTest` |
| Strength enforced (>=12, letter+digit, not common, not account-name) | `Uix3PlatformAdminProvisionCommandTest` |
| Password hashed; never logged/echoed/stored in plaintext | `Uix3PlatformAdminProvisionCommandTest` |
| Idempotent create/promote; `--rotate-password` gates rotation | `Uix3PlatformAdminProvisionCommandTest` |
| No seeded default admin exists | `Uix3PlatformAdminProvisionCommandTest` |
| Redacted `platform.admin.provisioned` log (`email_hash` only) | `Uix3PlatformAdminProvisionCommandTest` |

## UI / responsive / accessibility — feature test vs runtime verification

Feature tests assert the server-rendered contract (status codes, presence of
markup/attributes, headers, redaction). Purely visual and pixel/viewport
behaviour is confirmed at the deploy window via runtime verification.

| Check | Feature test | Runtime verification |
| --- | --- | --- |
| Route returns 200 / correct redirect | Yes (`Uix3*`) | `curl` smoke of `/admin/login` (200), dashboard/tenants after auth |
| Security headers present (`no-store`, noindex) | Yes (`Uix3AdminSecurityTest`) | `curl -I` over the operator channel |
| Redaction of tenant data in rendered output | Yes (`Uix3ControlCenterTest`) | Spot-check rendered pages |
| Skip link, aria-expanded nav, aria-current, labelled fields present | Partial (markup assertions where covered) | Visual/a11y check at viewports |
| Visible focus, reduced-motion, password show/hide (aria-pressed) | n/a (visual) | Manual/visual check |
| Responsive at 360/390/412/768/1024/1280/1440/1920, no horizontal overflow | n/a (visual) | Visual check at listed widths |
| Indonesian copy | Partial (string presence) | Visual review |

Note: UIX-3 adds **no migrations**, so no schema/migration test gating is
involved; the suite runs against the existing schema.
