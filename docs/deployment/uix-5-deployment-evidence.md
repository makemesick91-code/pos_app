# UIX-5 Deployment Evidence — Subscription, Billing & Invoice Console

> STATUS: DEPLOYED + RUNTIME-VERIFIED + DMS-NON-REGRESSED. Real observed output
> from the pilot shared VPS (`srv1730088` / daengtisiams-vps), captured
> 2026-07-13 (UTC) over an authenticated SSH operator channel. No placeholder
> values (rule 90 / UIX5-R028).

## 1. Release identity — EXACT MATCH
- Release commit (local `main`): `0214827d0cf0d100ccb2be1d48f0d6c3624f0006`
- `origin/main`: `0214827d0cf0d100ccb2be1d48f0d6c3624f0006`
- VPS `/var/www/aish-pos` HEAD after checkout: `0214827d0cf0d100ccb2be1d48f0d6c3624f0006`
- **local == origin == VPS: CONFIRMED**
- Code PR: #50 (MERGED 2026-07-13T00:36:20Z, merge commit `0214827`).

## 2. Authoritative CI
- PR #50 checks: **324 pass, 0 fail** at merge. The authoritative `pull_request`
  workflows (incl. `UIX-5 CI` — billing console gate + backend regression) were
  green. One transient `Set up Android SDK` failure in the unrelated Sprint 29 CI
  Android job (UIX-5 has zero Android changes) was confirmed as an infra flake and
  re-run to green — never overridden (rule 70).
- Post-merge `main` push workflows re-run the same validated tree.

## 3. Backup (rule 80)
- `aish_pos_pilot` backup: `/var/backups/aish-pos/aish_pos_pilot_pre_uix5_20260713-003702.sql.gz`
  (42K gzip, 105 `CREATE TABLE` statements).
- Rollback commit recorded: `c740a72b971c12e629d1d6f35cee2c1699bd3fbb` (UIX-4 GO).

## 4. Deploy actions
- `git fetch --all --tags` + `git checkout 0214827…`; VPS HEAD → `0214827`.
- `composer install --no-dev --optimize-autoloader`: DONE (53 packages).
- Migrations: **none pending** for UIX-5 (no schema change); `migrate:status` all Ran.
- Cache rebuild: `config:cache`, `route:cache`, `view:cache` all succeeded — all
  Blade views (incl. the 8 new billing views + `<x-rupiah>` + status-badge/pager
  partials + invoice-document) compiled cleanly on PHP 8.5.
- Ownership (UIX5-R026): `chown -R www-data:www-data storage/framework bootstrap/cache`;
  post-check `bootstrap/cache` = `www-data:www-data`, **0 root-owned files under
  `storage/framework`**.
- `nginx -t` OK; `systemctl reload php8.5-fpm nginx`. php8.3-fpm untouched (active).

## 5. Runtime verification
Encrypted operator channel (SSH → `127.0.0.1:8080`) and public HTTPS
(`aishpos.online`, Certbot TLS → proxy `:8080`):
- `GET /health/live` → 200; `GET /health/ready` → 200.
- `GET /owner/billing` (unauth) → **302 → /owner/login** (gated by `tenant.owner.web`).
- `GET /admin/billing` (unauth) → **302 → /admin/login** (gated by `platform.admin.web`).
- `GET /owner/billing/invoices/1/download` (unauth) → **302** (authenticated download,
  no public access — UIX5-R007).
- Web console billing routes registered (owner + admin billing + per-tenant panel).
- HTTPS: `https://aishpos.online/health/live` → 200; `https://aishpos.online/owner/billing`
  → 302 to login; `http://aishpos.online/…` → **301 → https** (no public plaintext
  billing access — UIX5-R025 satisfied; a domain + TLS is now provisioned).
- Laravel log: **0 new errors after the 00:37 deploy**. (3 pre-existing
  `touch(): Utime failed` entries are stale — timestamped 2026-07-12 22:09:45,
  ~2.5h before deploy — and are addressed by the ownership fix above.)
- Authenticated owner/admin billing flow, invoice detail, cross-tenant/cross-surface
  denial, financial integrity, and download security are verified by the 39 `Uix5*`
  feature tests in CI (against sqlite, not production financial data). Production
  authenticated click-through was intentionally NOT performed to avoid creating or
  altering real tenant financial records; see the GO decision note.

## 6. DaengtisiaMS non-regression (rule 80)
- DMS HEAD before & after: `8b0bb6af0a11624d34887e5b70e3a0c7627e34b4` — **UNCHANGED**.
- DMS services: php8.3-fpm `active`, nginx `active`, postgresql `active` (unchanged).
- DMS app responds `302` at `/` (default `:80` server) — serving normally.
- No DMS file, unit, or database touched by the deploy (Aish resources only:
  `/var/www/aish-pos`, php8.5-fpm pool, port 8080).

## 7. GO decision
- Preconditions met: authoritative CI green, deploy successful, runtime verified,
  DMS non-regressed, real evidence captured, local == origin == VPS.
- **GO** — operator (makemesick91@gmail.com) reviewed the observed evidence above
  and authorized the release on 2026-07-13. Authenticated billing behavior is
  covered by the 39 `Uix5*` CI tests plus production view-compilation and
  route-gating verification; production authenticated click-through was
  intentionally omitted to avoid creating/altering real tenant financial records.
- Annotated GO tag `uix-5-subscription-billing-invoice-console-go` is applied to
  the final release commit (this evidence-closure merge), which equals
  `origin/main` and the VPS `/var/www/aish-pos` HEAD at tag time.
