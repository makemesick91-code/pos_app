# Production Operations GO / WATCH / NO-GO

Sprint 19 — Production Operations Baseline & Post-Handover Governance Foundation.

The decision contract for the post-handover production operations gate, produced
by `production:post-handover-go-no-go`.

## GO criteria

- All required gates pass (release, RC/UAT, deployment/field, monitoring/
  hypercare, stabilization, closure/handover, operations).
- Production operations health is GO (all signals PASS).
- No open P0/P1 incident without a valid accepted risk.
- No SLA-breached P0/P1 incident.
- Backup/restore governance present and GO.
- Support/SLA governance present and GO.
- Release/rollback governance present and GO.
- Maintenance governance GO (no HIGH/CRITICAL active window without rollback plan).
- All required operations docs/checklists exist.

## WATCH criteria

- No blocking failure, but at least one non-critical warning.
- Open P2 incident with documented mitigation.
- Approved / valid accepted risk exists.
- Planned HIGH/CRITICAL maintenance **with** a rollback plan.
- A non-critical health signal WARNs.

## NO-GO criteria

- Any release/RC/UAT/deployment/monitoring/stabilization/handover gate fails.
- Open P0/P1 incident without a valid accepted risk.
- Expired blocking accepted risk.
- SLA-breached P0/P1 incident.
- Missing required operations docs/checklists.
- HIGH/CRITICAL maintenance window without a rollback plan reference.
- A critical health signal FAILs.

## Required evidence

- `production:ops-health --json`
- `production:incident-summary --json`
- `production:backup-governance-check --json`
- `production:post-handover-go-no-go --json`
- Latest `production_operation_runs` record with approver.

## Required commands

`production:ops-health`, `production:incident-summary`,
`production:backup-governance-check`, `production:post-handover-go-no-go`, plus
every cumulative Sprint 13–18 release/pilot/handover command.

## Required CI gates

Sprint 19 CI runs the smoke script, backend tests, the release gate, RC/UAT gate,
deployment/field gate, monitoring/hypercare gate, stabilization/defect gate,
closure/handover gate, the production operations gate, and Android
assembleDebug + testDebugUnitTest.

## Safety rules

- No secrets, credentials, APK/AAB, keystores, or customer data in evidence.
- No automatic production deployment.
- No real backup/restore execution.
- No real Slack/WhatsApp/email alert sending.
