# 72 — Authoritative CI Consolidation & Evidence Path (CICD-CTRL-2)

Extends rule 70 (CI & Runtime Control / CICD-CTRL-1). Rule 70's Safe CI Runtime
Control principles remain in force; this rule adds the single-authoritative-full-CI
and evidence-path discipline. Optimization means **eliminate redundant execution
while preserving required validation** — never reduce validation until CI turns green.

## Architecture (four lanes)
- **Lane B — Authoritative PR CI** (`.github/workflows/ci-authoritative.yml`,
  `on: pull_request`): exactly one complete authoritative validation per final
  source candidate. A fail-closed classifier picks the lane; concurrency cancels
  stale SHAs; `authoritative-summary` is the single truthful gate.
- **Lane C — Main integrity smoke** (`ci-main-smoke.yml`, `on: push:[main]`):
  source-equivalence + deployability smoke; escalates to full CI when equivalence
  is unproven.
- **Lane D — Strict evidence/docs validation** (`_evidence-validation.yml`):
  lightweight, allowed ONLY when the classifier confirms the strict allowlist.
- **Lane A — Development targeted checks**: changed-scope jobs (advisory).
- Shared logic lives in reusable workflows `_backend-tests`, `_android-build`,
  `_foundation-gates`, `_security-validation`, `_evidence-validation`.
- The 45 legacy `sprint*-ci` / `uix*-ci` workflows are **neutralized to
  `workflow_dispatch` (manual-only)** — kept and runnable, never deleted, no
  required-check name lost.

## Rules
- **CICD2-R001** — Exactly one complete authoritative full CI is required for each
  final source candidate commit.
- **CICD2-R002** — Development pushes use targeted validation and do not
  automatically duplicate authoritative full PR CI.
- **CICD2-R003** — A source change after a green authoritative run invalidates the
  previous result and requires a new authoritative run.
- **CICD2-R004** — Docs/evidence lightweight CI is permitted only after the
  fail-closed repository-owned classifier (`scripts/ci/classify_changes.sh`)
  confirms a strict approved allowlist.
- **CICD2-R005** — Rules, workflows, scripts, dependencies, schemas, configs,
  tests, deployment files, and source files are never classified as lightweight
  documentation changes.
- **CICD2-R006** — Unknown, mixed, renamed, or deleted sensitive paths require full
  authoritative CI (the classifier fails closed to `full_ci_required=true`).
- **CICD2-R007** — Main post-merge smoke may avoid full-suite duplication only when
  tested-source equivalence is structurally proven (git tree-hash match via
  `scripts/ci/verify_source_equivalence.sh`).
- **CICD2-R008** — If source equivalence cannot be proven, main must escalate to
  full CI.
- **CICD2-R009** — Concurrency may cancel stale runs only when a newer authoritative
  run exists for the same PR.
- **CICD2-R010** — The sole current authoritative run must never be cancelled as a
  runtime-saving shortcut (main-smoke uses `cancel-in-progress: false`).
- **CICD2-R011** — Duplicate workflows must be consolidated through reusable
  workflows or retired only after dependency and required-check analysis.
- **CICD2-R012** — Security, tenancy, financial integrity, offline/sync, foundation,
  deployability, and release governance gates may not be weakened for runtime
  savings (backend suite, all governance smokes, and all-variant Android build are
  preserved, run once).
- **CICD2-R013** — Mandatory failures may not use `continue-on-error` or be converted
  into warnings.
- **CICD2-R014** — Infrastructure flake classification requires step-level evidence
  and a successful controlled rerun without source change
  (`docs/ci/cicd-ctrl-2-flake-policy.md`).
- **CICD2-R015** — Cache and artifact reuse must be source-aware (cache keys include
  lockfile/runtime inputs) and must never replace required test execution.
- **CICD2-R016** — Untrusted pull-request code must not receive production,
  deployment, signing, payment, database, or SSH secrets.
- **CICD2-R017** — `pull_request_target` must not execute untrusted checked-out
  pull-request code.
- **CICD2-R018** — Evidence closure commits must prove application source
  equivalence to the fully tested candidate (`scripts/ci/validate_evidence.sh`).
- **CICD2-R019** — A lightweight evidence PR that changes executable or governing
  content automatically escalates to full CI (classifier guard in the evidence
  lane).
- **CICD2-R020** — The final release commit must have sufficient CI provenance,
  evidence validation, source synchronization, and GO-tag exact-match proof.
- **CICD2-R021** — Prior GO tags are immutable.
- **CICD2-R022** — CI optimization must be measured using observed workflow counts
  and runtime before and after implementation.
- **CICD2-R023** — CI runtime savings never override absence-of-proof governance.
- **CICD2-R024** — Shared-VPS source synchronization must not regress DaengtisiaMS.

## Enforcement
- `scripts/ci/cicd_ctrl_2_gate.sh` validates this architecture (structural +
  behavioral classifier assertions) and runs inside the authoritative CI foundation
  lane and locally.
- Because `main` is not branch-protected, the authoritative gate is enforced by rule
  and reviewer discipline: do not merge unless the authoritative PR CI for the final
  candidate SHA is green with no pending mandatory job.
