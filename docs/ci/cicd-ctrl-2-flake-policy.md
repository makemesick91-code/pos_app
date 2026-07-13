# CICD-CTRL-2 — Infrastructure Flake Policy

A CI failure may be classified an **infrastructure flake** (and rerun) ONLY when
step-level evidence shows all of (CICD2-R014):

1. The failure occurred **before** the relevant application tests/build compiled.
2. The cause is external: network, runner image, or a tool/SDK download
   (e.g. `android-actions/setup-android` repository timeout, GitHub package
   outage, transient runner failure).
3. Application source is **not** implicated.
4. A rerun of the **affected authoritative job/workflow only** succeeds.
5. **No source change** was made between the failed and successful run.

## Never auto-classified as a flake

- Gradle/Kotlin compilation failure
- Test timeout caused by a deadlock
- Migration failure
- Dependency resolution failure from an invalid lock/config
- Lint failure
- Any application test failure
- Missing secret/config caused by a workflow defect

These are real failures; fix the change.

## Procedure

- Rerun only the affected job/workflow (`gh run rerun <id> --failed`), never a
  blanket re-trigger of all workflows.
- Bounded retries (max 2), each logged with the observed step-level cause.
- No infinite retry loop; if it fails twice with the same external cause, treat it
  as an outage and pause rather than churn.

## Observed baseline example

At `3e12a32` (docs-only merge of PR #57 to `main`), `Sprint 7 CI` concluded
`failure` while the same workflow concluded `success` at the immediately preceding
SHA `2fa1463` with no backend/Android source change — a textbook Android-SDK/runner
flake, amplified 45× by unconditional full-CI-on-every-event. Under CICD-CTRL-2 a
docs-only merge takes the lightweight lane and does not build Android at all,
removing this entire class of amplified flake.
