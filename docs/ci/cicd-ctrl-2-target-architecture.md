# CICD-CTRL-2 — Target CI Architecture

## Goal

Turn the pattern of **~90 full workflow runs per commit** (45 on `push` + 45 on
`pull_request`, all unconditional) into:

- code source PR → **1** authoritative full CI for the final candidate
- evidence-only PR → **1** lightweight evidence CI
- `main` → **1** fast integrity/deployability smoke
- development → targeted checks, not unconditional full CI

## Files added

Lane workflows:
- `.github/workflows/ci-authoritative.yml` — Lane B (authoritative PR CI)
- `.github/workflows/ci-main-smoke.yml` — Lane C (main integrity smoke)

Reusable workflows (`workflow_call`):
- `.github/workflows/_backend-tests.yml` — full `php artisan test` + consolidated governance
- `.github/workflows/_android-build.yml` — all-variant build + tests + endpoint contract + APK artifact
- `.github/workflows/_foundation-gates.yml` — foundation + design gates + CI architecture gate + classifier tests
- `.github/workflows/_security-validation.yml` — secret/forbidden-artifact scan
- `.github/workflows/_evidence-validation.yml` — Lane D strict evidence validation

Scripts:
- `scripts/ci/classify_changes.sh` — fail-closed change classifier
- `scripts/ci/run_backend_governance.sh` — consolidated per-sprint governance smokes
- `scripts/ci/verify_source_equivalence.sh` — tree-hash source equivalence
- `scripts/ci/validate_evidence.sh` — evidence content + source-unchanged validation
- `scripts/ci/cicd_ctrl_2_gate.sh` — CI architecture gate (structural + behavioral)

Tests:
- `tests/ci/classify_changes_test.sh` — 26 classifier scenarios

## Lane B — Authoritative PR CI

```
pull_request
 └─ classify (fail-closed)
     ├─ [full] backend  (reusable: full suite + governance)
     ├─ [full] android  (reusable: debug/pilot/release + tests + endpoint contract)
     ├─ [full] foundation (reusable: foundation + design + CI arch gate + classifier tests)
     ├─       security  (reusable: always)
     ├─ [light] evidence (reusable: strict docs/evidence)
     └─ authoritative-summary (always; truthful single gate)
```

- `concurrency: authoritative-pr-<pr#>` with `cancel-in-progress: true` cancels stale
  SHAs (CICD2-R009); the summary depends on **all** mandatory jobs and fails if any is
  not `success` — or is `skipped` when it should have run (guards malformed `if:`).
- `permissions: contents: read`.

## Lane C — Main integrity smoke

```
push: main
 └─ equivalence (tree-hash vs PR head via API)
     ├─ foundation (always)
     ├─ security (always)
     ├─ [equivalent]  deployability (boot + routes; no full suite)
     └─ [unproven]    escalate-backend + escalate-android (full reusable)
     └─ main-summary (always; truthful gate)
```

- `cancel-in-progress: false` — never cancel a running main/release check (CICD2-R010).

## Consolidation rationale

The full `php artisan test` suite was byte-identical across ~44 workflows and the
`assembleDebug` Android build across ~41. They now run **once** (backend suite once,
all-variant Android once). Per-sprint governance smokes and go/no-go gates are
preserved — run once in `run_backend_governance.sh` after a single dependency install
— so no validation is lost (CICD2-R011/R012). The all-variant Android build is a
*stronger* gate than the legacy single-variant `assembleDebug`.

## Expected outcome (see deployment evidence for observed figures)

- Full backend suite executions per source PR: ~44 → **1**
- Android builds per source PR: ~41 → **1** (multi-variant)
- Workflow runs per commit: ~90 → **1** authoritative (PR) / **1** smoke (main) /
  **1** lightweight (evidence PR)
