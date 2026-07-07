# Production Readiness Sign-off

Sprint 18 — Pilot Closure & Production Handover Foundation.

Sign-off records are **append-only** (`production_handover_signoffs`). A change of
mind adds a new record; prior records are never deleted. Run
`php artisan production:signoff-summary --json` for the aggregate.

## Sign-off roles

| Role | Responsibility |
|------|----------------|
| OWNER | Business/product acceptance of production go-live. |
| TECHNICAL | Backend + Android + database readiness. |
| SUPPORT | Support/SLA readiness and intake. |
| ADMIN | Platform admin / tenant operations readiness. |
| OPERATOR | Field operator readiness. |

Required roles for GO (config `production_handover.required_signoff_roles`):
**OWNER, TECHNICAL, SUPPORT**.

## Sign-off criteria

- Backend tests pass; Android `assembleDebug` + `testDebugUnitTest` pass in CI.
- Release readiness, RC/UAT, deployment/field, monitoring/hypercare, and
  stabilization gates all run successfully.
- Pilot closure decision is GO or WATCH (with documented risk).

## Decision meanings

| Decision | Meaning | Gate impact |
|----------|---------|-------------|
| APPROVED | Role accepts production go-live. | Contributes to GO. |
| APPROVED_WITH_RISK | Accepts with a documented, mitigated risk. | Forces WATCH. |
| REJECTED | Role blocks go-live. | Forces NO_GO. |
| PENDING | Not yet signed. | Keeps WATCH. |

## Evidence requirements

- Each sign-off may reference evidence (`evidence_reference`) — a link/id only,
  never a secret.

## Signature placeholder

| Role | Signer (name/placeholder) | Decision | Timestamp | Notes |
|------|---------------------------|----------|-----------|-------|
| OWNER | __________ | ______ | __________ | ______ |
| TECHNICAL | __________ | ______ | __________ | ______ |
| SUPPORT | __________ | ______ | __________ | ______ |
