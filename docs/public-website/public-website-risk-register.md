# Public Website Risk Register — Aish POS Lite

Sprint 21 — Public Website / Landing Page Readiness Foundation.

Backed by the `public_website_risks` table and `PublicWebsiteRiskGovernanceService`.

## Decision impact

- Open **CRITICAL/HIGH** without a valid accepted risk → **NO-GO**.
- Open **MEDIUM** without mitigation → **WATCH**.
- Accepted risk for CRITICAL/HIGH/MEDIUM requires **approver + reason + expiry**.
- Expired accepted risk (CRITICAL/HIGH) → **NO-GO**; (MEDIUM) → **WATCH**.

## Severity

`CRITICAL | HIGH | MEDIUM | LOW | INFO`

## Area

`CONTENT_CLAIM | PRICING_ALIGNMENT | PRIVACY | LEGAL_TERMS | LEAD_CAPTURE | SEO | PERFORMANCE | ACCESSIBILITY | SECURITY | OPERATIONS | OTHER`

## Risk table

| Ref | Area | Severity | Status | Owner | Mitigation | Accepted by | Expiry / review |
|-----|------|----------|--------|-------|------------|-------------|-----------------|
| _(none open at foundation)_ | — | — | — | — | — | — | — |

New risks are recorded via `POST /api/v1/admin/public-website-risks`. No secrets or
private customer data may be stored in this register.
