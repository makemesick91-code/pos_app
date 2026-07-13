# CI Runtime Control Governance (CICD-CTRL-1 + CICD-CTRL-2)

This document synthesises the CI governance for Aish POS. It is the narrative
companion to `.claude/rules/70-ci-runtime-control.md` (CICD-CTRL-1) and
`.claude/rules/72-authoritative-ci-consolidation.md` (CICD-CTRL-2).

## Principle

> Optimization means **eliminate redundant execution while preserving required
> validation**. It never means reduce validation until CI turns green.

## The single-authoritative model (CICD-CTRL-2)

| Lane | Workflow | Trigger | Purpose |
|---|---|---|---|
| A — Development targeted | (changed-scope jobs) | branch push | fast, advisory, changed-scope only |
| B — Authoritative PR CI | `ci-authoritative.yml` | `pull_request` | **exactly one** full validation per final source candidate |
| C — Main integrity smoke | `ci-main-smoke.yml` | `push: main` | source-equivalence + deployability; escalates to full if unproven |
| D — Strict evidence | `_evidence-validation.yml` | via Lane B | lightweight docs/evidence, allowlist-gated |

Reusable workflows carry shared logic once: `_backend-tests`, `_android-build`,
`_foundation-gates`, `_security-validation`, `_evidence-validation`.

The 45 legacy `sprint*-ci` / `uix*-ci` workflows are **neutralized to
`workflow_dispatch` (manual-only)** — retained and runnable on demand, never
deleted, so no historical validation or check name is lost.

## Fail-closed classification

`scripts/ci/classify_changes.sh` is the authoritative decision (GitHub path
filters are an optimization only). It emits `full_ci_required` plus granular
flags. It fails closed: an unresolved diff, an empty change set, an unknown path,
or any non-lightweight extension forces full CI. Only a change whose every file
matches the strict docs/evidence allowlist may take the lightweight lane. Rules,
workflows, scripts, dependencies, schema, config, tests, and source never qualify
(CICD2-R004/R005/R006).

## Source equivalence (main)

`scripts/ci/verify_source_equivalence.sh` compares the git **tree hash** of the
merged main commit with the tree hash of the associated PR head (obtained via the
GitHub API), which holds across merge/squash/rebase. Proven equivalence → main
runs only the deployability smoke; unproven → main escalates to the full reusable
backend + Android + foundation jobs (CICD2-R007/R008).

## Security posture

- Workflows default to `permissions: contents: read`; no `write-all`, no
  `contents: write` in CI-only workflows (CICD2-R016).
- No `pull_request_target` executing untrusted checked-out code (CICD2-R017).
- Caches are keyed on lockfile/runtime inputs and never substitute for tests
  (CICD2-R015).
- No `continue-on-error` on mandatory jobs; the `authoritative-summary` /
  `main-summary` jobs fail if any mandatory job is not `success` (or is skipped
  when it should have run) (CICD2-R013).

## Infrastructure flake policy

See `docs/ci/cicd-ctrl-2-flake-policy.md`. A failure is an infrastructure flake
only with step-level evidence (external/network/runner/tool-download, before app
tests) and a successful controlled rerun with **no source change**. Application,
compilation, migration, dependency-resolution, and lint failures are never
auto-classified as flakes (CICD2-R014).

## Enforcement

`scripts/ci/cicd_ctrl_2_gate.sh` validates the architecture (structural +
behavioral classifier assertions) inside the authoritative CI. `main` is not
branch-protected, so the authoritative gate is enforced by rule and reviewer
discipline: do not merge unless the authoritative PR CI for the final candidate
SHA is green with no pending mandatory job.
