# CICD-CTRL-2 — Deployment / Runtime Evidence (observed)

Sprint: **AISH POS CICD-CTRL-2 — Single Authoritative Full CI & Evidence Path Optimization**
CI/CD governance work; no application code change, so the VPS action is a **source
fast-forward only** (no migration, no composer install, no service restart).
All figures below are observed from GitHub Actions and the VPS on 2026-07-13.

## Commit trail

- Baseline `main`: `3e12a32`
- Feature branch: `feature/cicd-ctrl-2-single-authoritative-full-ci-evidence-path-optimization`
- Code PR: **#58**
- Candidate `9300aa0` — authoritative CI run `29258233477` → **success**
- Final candidate `c3278d7` (adversarial fixes) — authoritative CI run `29258826549` → **success**
- Code merge commit: `f4bc44d` (merge of PR #58)
- Evidence PR: this PR (docs/evidence-only, lightweight lane)
- Final release commit + GO tag: recorded in the GO-tag annotation

## Observed baseline (before)

- 45 workflow files; every one `on: {push, pull_request}` with no branch/path filter;
  0 concurrency, 0 `permissions`, 0 reusable workflows.
- 1 commit to `main` = **45** full workflow runs (observed at `3e12a32`: 45 runs, all
  `push`). A PR commit = 45 (`push`) + 45 (`pull_request`) = **~90** for the same SHA.
- ~44 full `php artisan test` executions and ~41 Android builds per commit; docs-only
  changes cost the same.
- `Sprint 7 CI` flaked at `3e12a32` (success at prior SHA `2fa1463`, no source change).
- `main` not branch-protected (no required status checks).

## Observed after (measured)

| Event | Before | After (observed) |
|---|---|---|
| PR commit (`c3278d7`, PR #58) | ~90 workflow runs | **1** — `AISH POS Authoritative PR CI` only |
| Push to `main` (`f4bc44d`) | 45 workflow runs | **1** — `AISH POS Main Integrity Smoke` only |
| Docs/evidence PR | ~90 full runs | **1** lightweight (this PR) |
| Full `php artisan test` per source PR | ~44 | **1** |
| Android builds per source PR | ~41 | **1** (all-variant: debug/pilot/release) |

- Authoritative CI (`29258826549`, `c3278d7`): classify ✓, backend suite ✓, consolidated
  governance ✓, all-variant Android ✓, foundation+design+CI-arch gate ✓, security ✓,
  evidence skipped, authoritative-summary ✓. No mandatory job pending.
- Main integrity smoke (`29259052399`, `f4bc44d`): equivalence **proven** (tree-hash match
  + authoritative-CI-success check), deployability smoke ✓, foundation ✓, security ✓,
  escalate-backend/android **skipped**, main-summary ✓.
- Legacy `sprint*-ci`/`uix*-ci` (45) neutralized to `workflow_dispatch`; none auto-fired
  on PR #58 or on the `main` push (observed count = 1 each).

### Lane D (evidence-only) live test — PR #59

- This evidence PR changes only `docs/deployment/cicd-ctrl-2-deployment-evidence.md`.
  Authoritative run `29259428832` (head `5bada5e`): classify ✓, security ✓, **evidence ✓**,
  backend/android/foundation **skipped**, authoritative-summary ✓. A docs/evidence change
  no longer triggers the backend suite or any Android build.

### Escalation live test — throwaway PR #60 (cancelled, not merged)

- A branch mixing an evidence-doc edit **and** a `scripts/ci/run_backend_governance.sh`
  change: authoritative run `29259547163` classified `full_ci_required=true` — backend,
  consolidated governance, foundation, and all-variant Android jobs all started while the
  evidence job was **skipped**. The run was cancelled after the `classify` job proved
  escalation (to avoid a needless Android build) and PR #60 was closed unmerged and its
  branch deleted (CICD2-R019).

## Adversarial review (addressed before final candidate)

Independent review found and fixed, in `c3278d7`: (1) equivalence now also requires the
authoritative PR CI concluded `success` for the candidate before `equivalent=true`;
(2) main-smoke equivalence job granted `pull-requests:read`+`actions:read`; (3) Android
secret-leak scan no longer a no-op (`|| true` removed); (4) classifier `emit` strips CR/LF
to prevent `$GITHUB_OUTPUT` injection.

## VPS source synchronization (observed)

- Target: `/var/www/aish-pos` on the shared VPS (`daengtisiams-vps`, host `srv1730088`).
- Pre-sync HEAD `3e12a32` → post-sync HEAD `f4bc44d` (fast-forward; 69 files, all under
  `.github/`, `scripts/`, `tests/ci/`, `docs/`, `.claude/`, `CLAUDE.md`, `AGENTS.md`).
- No migration, no composer install, no service restart.
- `backend/storage/framework` and `backend/bootstrap/cache` ownership preserved
  (`www-data:www-data`).
- Health unchanged: `/`=200, `/health/live`=200 (`{"status":"ok"}`), `/health/ready`=200,
  `https://aishpos.online/`=200, `http://aishpos.online/`=301 redirect.
- Services active: `php8.5-fpm`, `aish-pos-queue-worker`, `nginx`.
- Final fast-forward to the release commit + local/origin/VPS exact match: recorded in the
  GO-tag annotation.

## DaengtisiaMS non-regression (observed)

- Path `/var/www/asia-dental-lab-v2`; HEAD `8b0bb6a` **unchanged** before and after; worktree clean.
- `php8.3-fpm` active; DMS `/`=404 (its normal baseline, unchanged). No source/migration/
  ownership change. **Non-regressed.**

## UIX-7 constraint

CI runtime optimization does **not** change UIX-7 physical-device GO status. The UIX-7 GO
tag remains operator-gated on real on-device evidence and is neither created nor moved here.
