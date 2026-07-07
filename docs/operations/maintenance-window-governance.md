# Maintenance Window Governance

Sprint 19 — Production Operations Baseline & Post-Handover Governance Foundation.

Governs planned production maintenance windows recorded in
`production_maintenance_windows` (admin API
`/api/v1/admin/production-maintenance-windows`). A maintenance window record
**never performs a deployment** — it is a governance/scheduling record only.

## Planned maintenance intake

1. Record the window: title, description, scheduled start/end, risk level, owner.
2. Attach a rollback plan reference for HIGH/CRITICAL windows.
3. Move through `PLANNED → APPROVED → IN_PROGRESS → COMPLETED` (or `CANCELLED` /
   `BLOCKED`). Actual start/end timestamps are stamped on transition.

## Risk level

| Risk | Meaning | Rollback plan |
| --- | --- | --- |
| LOW | No user-facing impact expected | Recommended |
| MEDIUM | Brief degradation possible | Recommended |
| HIGH | User-facing impact / schema change | **Required** |
| CRITICAL | Platform-wide impact / irreversible risk | **Required** |

## Rollback reference requirement

A HIGH/CRITICAL active window **without** a `rollback_plan_reference` forces
**WATCH/NO-GO** in `MaintenanceWindowService::summary()` and the post-handover
gate. The rollback plan references `release-rollback-governance.md`.

## Approval requirement

A window must reach `APPROVED` before `IN_PROGRESS`. Approval owner is the
Operations owner (or delegate). A `BLOCKED` window forces WATCH.

## Evidence requirement

Each window records an evidence reference (change ticket, checklist run) and the
owner. Post-maintenance evidence is attached on `COMPLETED`.

## Post-maintenance review

After `COMPLETED`, review: did it stay within the scheduled window, was rollback
needed, were there follow-up incidents? Record findings for the next ops run.

## Safety rules

- A maintenance window record never triggers a real deployment.
- No credentials, server IPs, or secrets in the window metadata.
