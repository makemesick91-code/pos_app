# Commercial Risk Register — Aish POS Lite

Sprint 20. Persisted in `commercial_launch_risks`, managed via
`/api/v1/admin/commercial-risks`, evaluated by `CommercialRiskGovernanceService`.
No secrets or private customer data are stored.

## Risk table (template)

| Ref | Area | Severity | Status | Owner | Mitigation | Accepted risk | Expiry / review |
| --- | --- | --- | --- | --- | --- | --- | --- |
| CRISK-… | PRICING | MEDIUM | OPEN | Sales lead | Pricing review scheduled | — | — |
| CRISK-… | SUPPORT_CAPACITY | HIGH | ACCEPTED_RISK | Ops lead | Temp. reduced SLA | Yes | 2026-08-01 |
| CRISK-… | LEGAL_TERMS | LOW | CLOSED | Owner | Terms published | — | — |

## Severity → decision impact

| Severity | Open (unaccepted) | Notes |
| --- | --- | --- |
| CRITICAL | **NO-GO** | Requires valid accepted risk to proceed. |
| HIGH | **NO-GO** | Requires valid accepted risk to proceed. |
| MEDIUM | **WATCH** | Requires documented mitigation. |
| LOW | GO | Tracked only. |
| INFO | GO | Informational. |

## Accepted-risk governance

Accepting a CRITICAL / HIGH / MEDIUM risk requires:

- an **approver**,
- a **reason**, and
- an **expiry / review date**.

An expired accepted risk reverts to its severity impact (CRITICAL/HIGH → NO-GO,
MEDIUM → WATCH).

## Areas

PRICING, PACKAGE_SCOPE, SALES_ENABLEMENT, ONBOARDING_CAPACITY, SUPPORT_CAPACITY,
BILLING_POLICY, LEGAL_TERMS, OPERATIONS, TECHNICAL, OTHER.
