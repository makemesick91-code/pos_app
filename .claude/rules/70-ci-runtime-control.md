# 70 — CI & Runtime Control

Continuous integration authority and runtime guardrails.

## Authoritative CI
- The `pull_request` GitHub workflows are the authoritative gate. Local test runs are
  advisory only; a change is not "green" until the PR workflows pass.
- CI runs on PHP 8.5 (the production runtime). Do not lower the CI PHP version to make a
  build pass.
- Do not merge on local pass alone, and do not merge with a required workflow red,
  cancelled, or skipped-to-bypass.

## Required checks
- Backend test suite, the applicable design gate(s)
  (`uix1`/`uix2`/`uix3_design_gate.sh`), and rule/foundation verification
  (`verify_application_foundation_rules.sh`) must all pass on the PR.
- Infra flakes (SDK/zip/network) are re-run, not overridden. Only re-run after confirming
  the failure is infrastructure, not the change.

## Runtime control
- Runtime governance/guardrail checks (lifecycle, entitlement, usage, export, gateway,
  observability go/no-go) are enforced in code and exercised in CI. Do not weaken a
  guardrail to pass a test — fix the change.
- Scheduled/queued work runs under the `aish-pos-queue-worker` systemd unit on the VPS;
  CI must not depend on production runtime state.

## Merge discipline
- One concern per PR where practical; each PR leaves `main` green and deployable.
- Merges to `main` happen via reviewed pull requests, never direct pushes that skip CI.

## Authoritative CI consolidation (CICD-CTRL-2)
- The authoritative gate is a single consolidated workflow, **AISH POS Authoritative
  PR CI** (`.github/workflows/ci-authoritative.yml`), running exactly one complete
  validation per final source candidate. Development pushes use targeted checks;
  `main` runs an integrity/deployability smoke that escalates to full CI unless
  tested-source equivalence is proven. Legacy `sprint*-ci`/`uix*-ci` workflows are
  neutralized to `workflow_dispatch` (manual-only), never deleted.
- Lightweight docs/evidence CI is allowed only when the fail-closed classifier
  (`scripts/ci/classify_changes.sh`) confirms the strict allowlist; rules, workflows,
  scripts, deps, schema, config, tests, and source never qualify. See
  `.claude/rules/72-authoritative-ci-consolidation.md` (CICD2-R001..R024) and
  `docs/governance/ci-runtime-control.md`. Optimization eliminates redundant
  execution; it never weakens a required gate to turn CI green.
