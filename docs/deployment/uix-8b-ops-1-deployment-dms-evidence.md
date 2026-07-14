# UIX-8B-OPS-1 — VPS Deployment & DaengtisiaMS Non-Regression Evidence

Shared VPS `srv1730088` (145.79.13.224). Aish POS deployed via Git fast-forward;
DaengtisiaMS (co-tenant) bracketed before and after. Captured during
UIX-8B-OPS-1 on 2026-07-14 (UTC).

## Change deployed
- `111799a` → `97fbb64` (current `origin/main`; descendant of UIX-8B `090e1d8`,
  includes the Bluetooth least-privilege fix `97fbb64`).
- Diff scope: `android/`, `docs/`, `scripts/`, `.claude/`, `CLAUDE.md`,
  `.github/` only. **Zero** backend source, migrations, or composer changes →
  no migrate, no composer install, no backend cache rebuild required
  (UIX8BOPS-R008/R009/R010).

## Aish POS deployment
| Item | Before | After |
|---|---|---|
| HEAD | `111799a` | `97fbb64` |
| Branch | `main` | `main` |
| Worktree | clean | clean |
| `storage/framework` owner | `www-data:www-data` | `www-data:www-data` |
| `bootstrap/cache` owner | `www-data:www-data` | `www-data:www-data` |
| Root-owned runtime files | — | none |
| HTTPS root | 200 | 200 |
| `/health/live` | 200 | 200 |
| `/health/ready` | 200 | 200 |
| aish-pos-queue-worker / nginx / postgresql / php8.5-fpm | active | active |

- Mechanism: `git fetch origin main` + `git merge --ff-only 97fbb64` in
  `/var/www/aish-pos` (official traceable Git mechanism, UIX8BOPS-R001/R002).
- Rollback point recorded: `111799a` (UIX8BOPS-R013).
- Remote: `https://github.com/makemesick91-code/pos_app`.
- Exact-match on `main`: local = origin = VPS = `97fbb64` (UIX8BOPS-R003).

## DaengtisiaMS non-regression bracket (`/var/www/asia-dental-lab-v2`)
| Item | Before | After |
|---|---|---|
| HEAD | `8b0bb6a` | `8b0bb6a` (unchanged) |
| Branch | `feature/sprint-26-phase-26-8-stabilization-closure-go-watch-no-go-report` | unchanged |
| Worktree | clean | clean |
| php8.3-fpm / nginx / postgresql | active | active |
| daengtisiams-queue-worker | active | active |

- DMS HEAD, worktree, config, and DB untouched (UIX8BOPS-R016..R020); no Aish
  script ran against DMS. **Result: DMS NON-REGRESSED** (UIX8BOPS-R022) →
  `UIX8_DMS_OK=true`.

## Pre-existing unrelated failure (not a regression)
- `logrotate.service` reported `failed` **both before and after** this deploy.
  It predates the change and is unrelated to Aish POS or the deployed diff
  (UIX8BOPS-R021). Not introduced by this deployment.

## Honest status
Deployment and DMS bracket PASS. This does **not** produce a UIX-8 GO — the GO
tag additionally requires operator-observed runtime evidence (21 scenarios,
still PENDING) and UIX-7 debt closure/waiver. Terminal state:
`IMPLEMENTATION COMPLETE — GO DEFERRED`.
