# UIX-3 — Platform Admin Login & SaaS Control Center — Deployment Evidence

Real, observed values from the pilot shared-VPS deployment. GO requires observed
values (not placeholders); public plaintext-HTTP admin usage remains **NO-GO** —
the console is reached only via an encrypted operator channel (SSH tunnel/VPN).

- Sprint: AISH POS UIX-3
- VPS: `daengtisiams-vps` (srv1730088), AISH path `/var/www/aish-pos`, port 8080 (IP-restricted)
- Code release commit (deployed): `16a02178d3e26b6248ed3c2065b09e5365329096` (merge of PR #46)
- Deploy window (UTC): 2026-07-12T18:08:28Z → 18:16:08Z

## 1. Pre-deploy baseline (2026-07-12T18:06:02Z)
| Item | Value |
|---|---|
| AISH POS HEAD | `a4ead76` (UIX-2 GO), worktree clean |
| DaengtisiaMS HEAD | `8b0bb6a` (baseline), worktree clean |
| Services | php8.5-fpm active, php8.3-fpm active, nginx active, postgresql@16 active, both queue workers active |
| Failed units | 0 |
| AISH HTTP | `/` 200, `/health/live` 200, `/admin/login` **404** (no admin portal pre-deploy) |
| DMS HTTP | `/` 302 (healthy) |
| Memory / swap | 7.8Gi (5.1 free) / 2Gi (0 used) |

## 2. Backup (verified)
- `pg_dump` custom-format: `/var/backups/aish-pos/database/aish_pos_20260712_180707.dump` (556K)
- Verified by `/usr/local/sbin/backup-aish-pos` (runs `pg_restore --list` at creation): **Backup verified**.
- Backup dir permissions restrictive (root-only, 600 files).

## 3. Deploy (2026-07-12T18:08:28Z)
| Step | Result |
|---|---|
| git fast-forward | `a4ead76` → `16a02178…`, worktree clean (0 dirty) |
| Composer autoload | `dump-autoload -o --no-scripts` → 5708 classes (new classes included) |
| Migrations | `migrate:status` → **0 pending** (UIX-3 adds no migrations) |
| Cache rebuild (www-data) | config:cache + route:cache + view:cache → OK |
| nginx | `nginx -t` successful |
| FPM reload | `systemctl reload php8.5-fpm` (php8.3/DMS untouched) |
| Cache ownership | `bootstrap/cache/*.php` owned by `www-data:www-data` (no root-owned cache) |

## 4. Runtime smoke — unauthenticated
| Request | Code | Expect |
|---|---|---|
| `GET /` | 200 | public site intact |
| `GET /admin/login` | 200 | login renders ("Masuk Platform Admin") |
| `GET /admin` | 302 | guest redirected to login |
| `GET /admin/tenants` | 302 | guest redirected to login |
| `GET /health/live` | 200 | ok |
| `GET /health/ready` | 200 | ok |
| `POST /admin/login` (no CSRF token) | 419 | CSRF enforced |

## 5. Runtime smoke — authenticated (ephemeral verify admin, random password, deleted after)
| Step | Result |
|---|---|
| `platform:admin-provision --stdin-password` | admin created (and `--rotate-password` exercised) |
| `POST /admin/login` (valid) | 302 → dashboard |
| `GET /admin` (dashboard) | 200; renders "Total Tenant", "Kesehatan Operasional" (real metrics) |
| `GET /admin/tenants` (list) | 200; renders "Manajemen Tenant" |
| `GET /admin/tenants/{id}` (detail) | 200; renders tenant name + "Status Lifecycle (otoritatif)" |
| Security: detail HTML | no `$2y$` password hash, no `remember_token` (SEC_NO_HASH_LEAK) |
| `POST /admin/logout` | 302 → login |
| `GET /admin` after logout | 302 (session invalidated) |
| Rate limiting | observed lockout (throttle redirect) after repeated same-email attempts — control verified |

## 6. Audit trail
- Actions recorded: `ADMIN_LOGIN`, `TENANT_VIEWED` (cross-tenant view attributed to actor).
- Password-leak scan across `before_values`/`after_values`/`metadata` of recent rows: **0** matches.

## 7. Verification cleanup (no residue)
- Ephemeral verify admin + throwaway tenant + their audit rows deleted.
- Final pilot DB state: **tenants=0, platform admins=0, audit_logs=0** (pristine).
- Operator action required: run `php artisan platform:admin-provision` to create the operational admin (no default credentials are seeded by design).

## 8. DaengtisiaMS non-regression (2026-07-12T18:16:08Z)
| Item | Value |
|---|---|
| DMS HEAD | `8b0bb6a` — unchanged from baseline |
| DMS worktree | clean |
| DMS HTTP `/` | 302 (healthy, same as baseline) |
| php8.3-fpm / daeng queue | active / active |
| PHP 8.3 version | 8.3.6 — unchanged (no apt upgrade) |
| Cross-DB isolation | `aish_pos_user` → `asia_dental_lab_pilot` connection rejected |
| Failed units | 0 |

## 9. Final AISH state
- AISH HEAD `16a02178…`; `/admin/login` 200, `/` 200, `/health/ready` 200; php8.5-fpm + aish queue active.

## 10. HTTPS / network posture
- No domain/HTTPS yet. Admin console reachable only via encrypted operator channel (SSH tunnel/VPN); the :8080 endpoint is IP-restricted for a technical pilot.
- **Public plaintext-HTTP admin usage = NO-GO** (stated truthfully).

## 11. Final commit equality & GO
- Code release commit `16a02178…` deployed and runtime-verified.
- Evidence closure merge becomes the final release commit; the VPS is fast-forwarded to it (docs-only, no code/runtime change) and equality is verified local == origin/main == VPS HEAD before tagging.
- GO decision: **GO** for the restricted technical pilot (isolated, IP-restricted, encrypted operator channel), with public plaintext-HTTP admin explicitly NO-GO. Annotated GO tag `uix-3-platform-admin-login-saas-control-center-foundation-go` applied to the final release commit; existing GO tags remain immutable.
