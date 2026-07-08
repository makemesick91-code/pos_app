# Billing Collection Risk Register (Sprint 23)

Billing collection risks are tracked as `SaasBillingCollectionRisk` records and gate
the Billing Collection GO / WATCH / NO-GO decision.

## Severity → decision impact

| Severity | Open (no valid accepted risk) | Accepted risk (valid) |
|----------|-------------------------------|-----------------------|
| CRITICAL | **NO-GO** | WATCH until expiry, then NO-GO |
| HIGH | **NO-GO** | WATCH until expiry, then NO-GO |
| MEDIUM | **WATCH** (unless mitigated) | WATCH until expiry |
| LOW | informational | — |
| INFO | informational | — |

- Open CRITICAL/HIGH without a valid accepted risk ⇒ **NO-GO**.
- Open MEDIUM without a mitigation ⇒ **WATCH**.
- An **expired** accepted CRITICAL/HIGH risk re-blocks (**NO-GO**); an expired
  accepted MEDIUM risk ⇒ **WATCH**.

## Areas

`PAYMENT_DELAY`, `DISPUTE`, `INVOICE_ACCURACY`, `COLLECTION_SLA`,
`PACKAGE_ALIGNMENT`, `SUBSCRIPTION_STATUS`, `LEGAL_PRIVACY`, `ACCOUNTING_EXPORT`,
`MANUAL_EVIDENCE_QUALITY`, `OTHER`.

## Statuses

`OPEN → MITIGATED` / `ACCEPTED_RISK` / `CLOSED`.

## Accepted-risk requirements

Accepting a CRITICAL/HIGH/MEDIUM risk requires:

- an **owner/approver**,
- a **reason**, and
- an **expiry / review date**.

An accepted risk past its expiry re-blocks per the table above.

## Register

| Ref | Area | Severity | Status | Owner | Mitigation | Accepted-risk expiry |
|-----|------|----------|--------|-------|------------|----------------------|
| _(recorded via `POST /api/v1/admin/billing/risks`)_ | | | | | | |

Risks are created and governed through the platform-admin billing risk API; this
register documents the governance rules, while the live data is stored in
`saas_billing_collection_risks`.
