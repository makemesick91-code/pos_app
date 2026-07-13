# CICD-CTRL-2 — Deployment / Runtime Evidence

Sprint: **AISH POS CICD-CTRL-2 — Single Authoritative Full CI & Evidence Path Optimization**
This is CI/CD governance work; there is no application code change, so the VPS
action is a **source fast-forward only** (no migration, no composer install, no
service restart).

## Commit trail

- Baseline `main`: `3e12a32`
- Feature branch: `feature/cicd-ctrl-2-single-authoritative-full-ci-evidence-path-optimization`
- Code PR: (recorded at closure)
- Code merge commit: (recorded at closure)
- Evidence PR: (recorded at closure)
- Final release commit: (recorded at closure)
- GO tag: `cicd-ctrl-2-single-authoritative-full-ci-evidence-path-optimization-go`

## Observed baseline (before)

- 45 workflow files; every one on `push` AND `pull_request`, no branch/path filter.
- 1 commit to `main` = 45 full workflow runs (observed at `3e12a32`: 45 runs, all
  `push` event). A PR commit = 45 (`push`) + 45 (`pull_request`) = ~90 for the same
  SHA. Docs-only changes cost the same ~90.
- ~44 full `php artisan test` executions and ~41 Android builds per commit.
- `Sprint 7 CI` flaked at `3e12a32` (success at prior SHA `2fa1463`, no source change).
- `main` is not branch-protected (no required status checks).

## After (architecture)

- 1 authoritative full CI per final source PR candidate (`ci-authoritative.yml`).
- 1 lightweight evidence CI for docs/evidence-only PRs.
- 1 fast integrity/deployability smoke on `main` (`ci-main-smoke.yml`).
- 45 legacy workflows neutralized to `workflow_dispatch` (manual-only, retained).

## Observed CI runs (recorded at closure)

- Authoritative PR CI run (final source SHA): (recorded at closure)
- Main integrity smoke run: (recorded at closure)
- Evidence-only lightweight run: (recorded at closure)
- Escalation scenario (evidence + script → full): (recorded at closure)
- Concurrency stale-cancellation observation: (recorded at closure)
- Before/after run counts and runtime: (recorded at closure)

## VPS source synchronization (recorded at closure)

- Target: `/var/www/aish-pos` on the shared VPS (`daengtisiams-vps`)
- Pre-sync HEAD / post-sync HEAD: (recorded at closure)
- Fast-forward only; no migration / no composer install / no restart.
- `storage/framework` and `bootstrap/cache` ownership preserved (`www-data:www-data`).
- HTTPS root / `/health/live` / readiness: (recorded at closure)

## DaengtisiaMS non-regression (recorded at closure)

- Path `/var/www/asia-dental-lab-v2`; expected HEAD `8b0bb6a` (unchanged).
- php8.3-fpm / nginx / PostgreSQL / worker unchanged; `SELECT 1` OK.

## UIX-7 constraint

CI runtime optimization does **not** change UIX-7 physical-device GO status. The
UIX-7 GO tag remains operator-gated on real on-device evidence and is not created
or moved here.
