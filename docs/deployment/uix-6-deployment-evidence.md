# UIX-6 Deployment Evidence — Support, Observability & Incident Console

All values below are observed output from the release process on 2026-07-13
(shared VPS `srv1730088` / `daengtisiams-vps`). UIX-6 is read-only and adds no
schema migration.

## 1. Release identity — EXACT MATCH
- Code PR: **#52** (MERGED), code-merge commit `9865174683b393f4d756de7d112837905cdf0f66`.
- Deployed & authenticated-runtime-verified build: `9865174` on the VPS
  (`/var/www/aish-pos` HEAD after checkout = `9865174`).
- Final release commit = this deployment-evidence-closure merge; it becomes
  `origin/main` HEAD and the annotated GO-tag target. The VPS is re-checked-out
  to it and **local `main` == `origin/main` == VPS HEAD** is reconfirmed at tag
  time (the closure merge is docs-only; the verified runtime behaviour at
  `9865174` is unchanged). The exact-match sha is recorded in the GO-tag
  annotation.

## 2. Authoritative CI
- All PR #52 pull_request workflows green: **326 check-runs SUCCESS, 0 failures**
  (no infra flakes, no reruns needed).
- `UIX-6 CI` → `UIX-6 support/observability/incident console gate + backend
  regression`: **SUCCESS** (foundation gate + `uix6_design_gate.sh` chaining
  UIX-1..5 + UIX6-R001..R033 rule-presence + `--filter=Uix6` + full backend
  regression, PHP 8.5).
- PR mergeable state: `CLEAN` / `MERGEABLE`.

## 3. Backup (rule 80)
- `pg_dump` of `aish_pos_pilot` → `/root/backups/aish_pos_pilot_20260713_045325.sql.gz`.
- Rollback point recorded: prior Aish HEAD `0164009d6f07cd03865bb0b531170b80c8739c36`
  (the UIX-5 release commit).

## 4. Deploy actions
- `git fetch --all --tags && git checkout 9865174…`; VPS HEAD → `9865174`.
- `composer install --no-dev --optimize-autoloader`: DONE.
- Migrations: UIX-6 introduces none; `migrate --force` reported nothing pending.
- Cache rebuild: `config:cache`, `route:cache`, `view:cache` all succeeded.
- Ownership (UIX6-R031): `chown -R www-data:www-data storage/framework
  bootstrap/cache`; **0 root-owned files** under `storage/framework`
  (`www-data:www-data` confirmed on both paths).
- `nginx -t` OK; `systemctl reload php8.5-fpm nginx`. **php8.3-fpm untouched
  (active).**

## 5. Runtime verification
### 5a. Unauthenticated (public HTTPS)
- `https://aishpos.online/` → **200**; `http://aishpos.online/` → **301** (→ HTTPS).
- `/health/live` → **200**; `/health/ready` → **200**.
- `/admin/support`, `/admin/observability`, `/admin/incidents` (unauth) → **302
  → /admin/login**.
- `/owner/support` (unauth) → **302 → /owner/login**.

### 5b. Authenticated (UIX6-R029 — safe throwaway accounts + synthetic incidents)
Performed over public HTTPS with disposable Platform Admin and Tenant Owner
accounts and synthetic, non-sensitive incident records; all removed afterwards.
- **Platform Admin**: login **302**; `/admin/support`, `/admin/observability`,
  `/admin/support/tenants`, `/admin/incidents`, `/admin/incidents/{id}` → all
  **200**; `/admin/incidents/999999` → **404**.
- Incident detail redaction (UIX6-R009/R019): synthetic secret token
  (`sk_live_…`) **redacted**, email PII **redacted**, raw evidence storage path
  **not rendered** (only "Bukti: Ada").
- `Cache-Control: no-store` present on an authenticated admin page (UIX6-R020).
- POST logout → subsequent `/admin/support` → **302** (access revoked).
- **Tenant Owner**: login **302**; `/owner/support` → **200**, renders the
  owner's OWN incident and **not** the foreign tenant's; `/owner/support/incidents/{own}`
  → **200**; `/owner/support/incidents/{foreign}` → **404** (cross-tenant denied,
  UIX6-R004/R008); `/admin/observability` (owner session) → **302** (surface
  separation, UIX6-R005).
- **Synthetic-data cleanup**: all throwaway tenants/users/incidents hard-deleted;
  pristine state verified (`tenants=0 users=0 tsi=0 pinc=0`).
- No new Aish `production.ERROR` in the UIX-6 window (the 3 recent ERROR lines are
  pre-existing `touch(): Utime` cache warnings from 2026-07-12 22:09, unrelated).

## 6. DaengtisiaMS non-regression (rule 80 / UIX6-R032)
- DMS HEAD before & after deploy: `8b0bb6af0a11624d34887e5b70e3a0c7627e34b4` —
  **UNCHANGED**; `DMS_DIRTY=0`.
- php8.3-fpm, nginx, postgresql all **active** (unchanged); `aish-pos-queue-worker`
  + php8.5-fpm active. Ports 80/443/8080 intact.
- Only Aish resources touched (`/var/www/aish-pos`, php8.5-fpm pool `aish-pos`,
  port 8080). No DMS file, unit, or database modified.

## 7. GO decision
- Preconditions met: authoritative CI green (326/0), deploy successful,
  unauthenticated AND authenticated runtime verified (cross-tenant + surface
  separation + redaction + no-store + logout), synthetic data cleaned to pristine
  state, DMS non-regressed, HTTPS + HTTP→HTTPS 301 active, real (non-placeholder)
  evidence captured.
- **GO** — annotated GO tag `uix-6-support-observability-incident-console-go`
  applied to the final release commit (this evidence-closure merge) =
  `origin/main` = VPS HEAD at tag time. Prior GO tags remain immutable.
