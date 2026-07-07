# Release / Rollback Governance

Sprint 19 — Production Operations Baseline & Post-Handover Governance Foundation.

Governs release and rollback ownership for the post-handover production phase.
Complements the Sprint 13 `docs/release/release-go-no-go-runbook.md` and the
Sprint 18 `docs/handover/release-ownership-matrix.md`. This is a
governance/documentation check only — **Sprint 19 performs no automatic
deployment and no automatic rollback.**

## Release candidate

Every production release identifies a **release candidate** commit and GO tag
(e.g. the latest `sprint-19-...-go` tag on `main`). The candidate commit and tag
are recorded in the operation run evidence references. No APK/AAB or signing key
is committed — the Android artifact is built and signed outside this repository.

## Release owner

The **Technical owner** is the release owner: prepares the candidate, confirms
the readiness/RC/UAT/deployment/monitoring/stabilization/closure/handover gates,
and executes the release outside this repository.

## Rollback owner

The **Technical owner** is also the rollback owner. A named deputy is recorded in
the operator secret store so rollback is never single-person-blocked.

## Rollback checklist

1. Confirm the decision to roll back (Operations owner sign-off).
2. Redeploy the previous known-good release candidate (outside this repository).
3. If redeploy is insufficient, restore from a verified backup
   (`backup-restore-governance.md`).
4. Verify health (`production:ops-health`) and incident state
   (`production:incident-summary`).
5. Record the rollback as a production incident with evidence.

## Validation after rollback

After rollback, re-run `production:post-handover-go-no-go` and confirm the
decision returns to GO/WATCH. Record the validation evidence in the operation
run. Any residual issue is tracked as a production incident.

## Safety rules

- No automatic deployment in Sprint 19.
- No automatic rollback in Sprint 19.
- No signing keys, keystores, APK/AAB, or secrets in this repository.
