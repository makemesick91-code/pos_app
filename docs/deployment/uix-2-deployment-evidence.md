# UIX-2 Deployment Evidence

Status: **GO** — merged, deployed to the restricted shared-VPS pilot, runtime-verified, and DaengtisiaMS non-regression confirmed. HTTPS remains blocked until a public domain exists; the restricted HTTP endpoint is a technical pilot only and public real-data HTTP stays NO-GO.

## Continuous integration (authoritative)

All 40 authoritative pull-request workflows completed successfully. Thirty-four duplicate push-event workflows were cancelled under CI runtime control, six push-event workflows succeeded, and there were no failed or pending workflow runs. Check-runs on the head commit: 204 success, 110 cancelled (the duplicate push-event runs), 0 failed, 0 pending.

- Android source was unchanged after auditing the seven existing screens; the authoritative Android build ran in CI on JDK 21. Local JDK 25 was not used as release evidence.
- The public website is Blade/inline-CSS with no Node/Vite build pipeline and no committed lockfile, so no `npm` step was run.
- Lighthouse was unavailable, so no score was fabricated. Responsive rendering was validated with headless Chrome at 360, 390, 412, 768, 1024, 1280, 1440, and 1920 widths.

## Repository and merge

- Branch: `feature/uix-2-premium-public-website-visual-remediation`
- PR: #44 (state: MERGED)
- Feature commit: `7cb3d41` (verified ancestor of the merge commit)
- Code merge commit (PR #44): `b93f6f3`
- Final release commit: the evidence-closure merge on `main` that adds this record (the GO tag points at it; `b93f6f3` is its parent)
- Pre-UIX-2 baseline: `dbe2d38`
- Existing GO tags (`pilot-shared-vps-isolated-deployment-go`, `pilot-shared-vps-post-go-hardening-go`, `uix-1-complete-handoff-implementation-go`) were left unchanged.

## Pre-deploy backups (verified)

- Aish POS isolated pilot database: custom-format dump created by the established backup script, verified with `pg_restore --list` (non-empty).
- DaengtisiaMS database: read-only custom-format dump, verified with `pg_restore --list`, with sha256 and table-of-contents sidecars. DaengtisiaMS code was not touched.
- Configuration backup (chmod 700): application env file, Nginx site, PHP 8.5 pool, and queue-worker unit.

## Deployment (restricted shared VPS)

- Pre-deploy Aish POS HEAD: `dbe2d38` (clean working tree)
- Deployed Aish POS HEAD: `b93f6f3` for the code deployment (fast-forward pull, clean working tree, equals the PR #44 merge commit); the VPS is then fast-forwarded to the evidence-closure final release commit before the GO tag is applied.
- Changed files: PublicWebsite controllers and Blade views, public-route/experience tests, project rules, UIX-2 docs, responsive screenshots, and the UIX-2 design gate. No database migrations were introduced (`migrate:status` shows no pending migrations).
- Composer production install: `--no-dev --prefer-dist --optimize-autoloader` completed.
- Laravel caches rebuilt: config, route, and view caches.
- Queue worker restarted; PHP 8.5 FPM and Nginx validated and reloaded. PHP 8.3, its package holds, UFW restriction, and PostgreSQL isolation were left unchanged.

## Runtime smoke (deployed)

- Root, `/health/live`, `/health/ready`: all HTTP 200 with sub-100ms timings.
- Sensitive paths (`/.env`, storage log path): HTTP 403.
- App report: Laravel 13.x, PHP 8.5, environment production, debug off, config/routes/views cached.
- Runtime storage / queue status: GO — zero failed jobs, no pending jobs, queue worker active.
- No HTTP 500s, fatal exceptions, or repeated queue failures in application, Nginx, or PHP-FPM logs.

## Deployed visual and functional validation

- Premium sticky navbar with the Aish Tech Solution → Aish POS hierarchy and a working primary CTA; desktop navigation and an accessible mobile hamburger (Escape/`aria-expanded`).
- Split hero with a faithful Android cashier mockup, trust strip, feature cards, use-case cards, product showcase tabs (`role="tablist"`), onboarding workflow, offline/sync section, truthful QRIS lifecycle, plan cards with honest pilot pricing (no fabricated prices, metrics, or testimonials), capability proof, native `<details>` FAQ, final CTA, real lead form, and footer.
- Metadata verified in the rendered output: title, canonical, Open Graph, Twitter, `application/ld+json`, and meta description. All assets are inline/self-contained (no external CDN dependency). No dead `href="#"` links.
- Responsive: rendered screenshots captured from the deployed runtime at 360, 390, 412, 768, 1024, 1280, 1440, and 1920 widths; desktop premium layout and mobile collapse both correct with no horizontal overflow.
- Lead form end-to-end: a clearly-marked test submission returned HTTP 302 (success), persisted, and was then deleted so the table was restored to its pre-test baseline. No real lead data was polluted.

## DaengtisiaMS non-regression

- Pre-deploy and post-deploy HEAD identical and unchanged; working tree clean.
- HTTP root → 302 redirect to `/login`; `/login` → 200.
- Database `SELECT 1` succeeded.
- PHP 8.3.6 intact and FPM config valid; Nginx and PostgreSQL active.
- Resources healthy (swap active and unused, low load, no failed systemd units). No new application errors appeared after the deploy window.

## Remaining blocker

- Public HTTPS remains blocked until a domain is provisioned. The pilot stays restricted-access HTTP only; public real-data exposure remains NO-GO.

## GO tag

- `uix-2-premium-public-website-visual-remediation-go` (annotated) points at the final release commit (the evidence-closure merge) and matches local `main`, `origin/main`, and the VPS HEAD.
