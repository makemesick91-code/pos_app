# CICD-CTRL-2 — Baseline CI Audit (observed)

Sprint: **AISH POS CICD-CTRL-2 — Single Authoritative Full CI & Evidence Path Optimization**
Baseline commit: `3e12a32` (`origin/main`, HEAD at sprint start)
Repository: `makemesick91-code/pos_app`
Data source: `gh run list` / `gh api` observed on 2026-07-13. No estimates — figures below are read from GitHub Actions.

## 1. Workflow inventory

- **45** workflow files in `.github/workflows/` (`sprint0-ci.yml` … `sprint38-ci.yml` + `uix1/3/4/5/6/7-ci.yml`).
- **Every** workflow declares `on: { push:, pull_request: }` with **no `branches:` and no `paths:` filter** (branch-filtered: 0, path-filtered: 0, no-filter: 45).
- **0** workflows declare `concurrency:`.
- **0** workflows declare `permissions:` (all inherit the default, broader-than-needed token).
- **0** workflows use `workflow_dispatch:`, `schedule:`, or `workflow_call:` (no reusable workflows).
- **44 / 45** run the full backend suite (`php artisan test`).
- **41 / 45** set up JDK + Android SDK and build the app (gradle `assemble*`).

## 2. Observed duplication (the core problem)

Because every workflow fires on both events with no filter:

| Scenario | Observed workflow runs for one commit |
|---|---|
| Push of `3e12a32` to `main` | **45** (all `push` event; 36 success, 1 failure, 8 still queued when sampled) |
| A feature-branch push **with an open PR** | 45 (`push`) + 45 (`pull_request`) = **~90** for the *same SHA* |
| A **docs-only** evidence PR | identical **~90** full backend + Android runs for a Markdown change |

So a single source commit costs on the order of **44 full `php artisan test` executions** and **41 Android builds**, and a docs-only change costs exactly the same. There is **one** authoritative validation worth of signal buried inside ~45× redundant execution.

## 3. Observed flake

At `3e12a32` (the docs-only merge of PR #57 to `main`), **`Sprint 7 CI` concluded `failure`**, while the very same workflow concluded `success` at the immediately preceding SHA `2fa1463` with no backend/Android source change between them. This is a textbook **infrastructure flake** (Android SDK / runner transient) amplified 45× by unconditional full-CI-on-every-event. It is a pre-existing condition, not introduced by this sprint.

## 4. Structure of a representative workflow (`sprint30-ci.yml`)

Four jobs, each re-provisioning from scratch:
1. `foundation-and-smoke` — PHP 8.5 + composer install + sqlite env + foundation-doc check + PROJECT_RULES lock grep + `scripts/sprintNN_smoke.sh` + forbidden-file check.
2. `backend-tests` — composer validate + install + migrate + **`php artisan test`** (full suite).
3. `<domain>-governance-gate` — composer install + sprint `*:go-no-go --strict --json` + prior-sprint go-no-go chain.
4. `android-build-test` — JDK 21 + Android SDK + `assembleDebug` + `testDebugUnitTest`.

The newest, `uix7-ci.yml`, additionally builds `assemblePilot` + `assembleRelease`, runs all-variant unit tests, asserts per-variant `BuildConfig` endpoints (UIX7-R046/R049), and uploads APK artifacts.

**Redundancy:** composer install + the full `php artisan test` suite is byte-for-byte identical across ~44 workflows. The JDK/SDK/`assembleDebug` Android build is identical across ~41. Only the per-sprint **smoke script** and **`*:go-no-go` governance commands** differ — and those are cheap (bash + a few artisan calls) once dependencies are installed.

## 5. Governance / enforcement context

- `main` is **NOT** branch-protected (`gh api …/branches/main/protection` → 404). There are **no required status checks**. Per `CLAUDE.md` rule 70 the `pull_request` workflows are the *authoritative gate by discipline*, not by GitHub enforcement.
- Consequence for this sprint: consolidating/retiring workflows will **not** be blocked by required-check names, but the new authoritative gate must be enforced by **rule + reviewer discipline** (encoded as CICD2-R001..R024), and old workflows are **neutralized to `workflow_dispatch` (kept, made manual-only)** rather than deleted — reversible, no check-name loss.

## 6. Target (see `cicd-ctrl-2-target-architecture.md`)

Replace 45 unconditional full-CI workflows with four lanes driven by a fail-closed change classifier:
- **Lane B — Authoritative PR CI** (`ci-authoritative.yml`, `on: pull_request`): exactly one full validation per final source candidate, with concurrency cancelling stale SHAs.
- **Lane C — Main integrity smoke** (`ci-main-smoke.yml`, `on: push: main`): source-equivalence + foundation + deployability smoke, escalating to full when equivalence is unproven.
- **Lane D — Strict evidence validation**: lightweight docs/evidence checks, allowed only when the classifier confirms a strict allowlist.
- **Lane A — Development targeted checks**: changed-scope jobs, advisory.

Reusable workflows (`_backend-tests`, `_android-build`, `_foundation-gates`, `_security-validation`, `_evidence-validation`) carry the shared logic so it is defined once.
