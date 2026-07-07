# Commercial Launch Sign-off — Aish POS Lite

Sprint 20. Persisted in `commercial_launch_signoffs`, recorded via
`/api/v1/admin/commercial-launch-runs/{launchRun}/signoffs`. Records are preserved
and never carry secrets.

## Required sign-off roles

| Role | Responsibility |
| --- | --- |
| OWNER | Overall commercial go decision. |
| TECHNICAL | Release/operations technical readiness. |
| SALES | Sales enablement & package positioning. |
| OPERATIONS | Onboarding capacity & support readiness. |

(SUPPORT is an additional optional role.)

## Decision meanings

| Decision | Effect on launch |
| --- | --- |
| APPROVED | Contributes to GO. |
| APPROVED_WITH_RISK | Forces **WATCH**. |
| REJECTED | Forces **NO-GO**. |
| PENDING | Role not yet signed → missing role → WATCH. |

## Evidence requirements

Each sign-off should carry an `evidence_reference` pointing to the reviewed
material (gate outputs, risk register, package catalog). Notes are sanitized for
secrets before persistence.

## Sign-off record (placeholder)

| Role | Signer | Decision | Signed at | Evidence |
| --- | --- | --- | --- | --- |
| OWNER | __________ | ________ | __________ | __________ |
| TECHNICAL | __________ | ________ | __________ | __________ |
| SALES | __________ | ________ | __________ | __________ |
| OPERATIONS | __________ | ________ | __________ | __________ |
