# Lead Interest Policy — Aish POS Lite

Sprint 21 — Public Website / Landing Page Readiness Foundation.

## Interest-only submission

The public form (`POST /interest`) captures **interest only**. It stores a
`lead_interest_submissions` row and nothing else.

## No automatic tenant/user/subscription/device creation

A lead submission **never**:

- creates a tenant,
- creates a user,
- creates/activates a subscription,
- registers a device,
- sends a real email/WhatsApp,
- integrates with a real CRM.

Follow-up is a **manual, human** process performed by the sales/operations team via
the admin lead APIs.

## Validation rules

- `contact_name` required.
- `contact_email` required, valid email.
- `contact_phone`, `business_name`, `business_type` optional, length-bounded.
- `estimated_store_count` / `estimated_device_count` optional integers.
- `message` optional, max 2000 chars.
- Secret-looking input is stripped by `LeadInterestGovernanceService`.

## Consent requirement

An explicit consent checkbox is required; the consent timestamp is stored. Missing
consent → the submission is rejected.

## Rate limiting

`POST /interest` is throttled via the `public-interest` limiter (10/min per IP).

## Retention placeholder

Lead retention/erasure policy is a readiness placeholder to be finalized with
Legal/Privacy before commercial launch.

## Manual follow-up flow

`NEW → REVIEWED → CONTACTED → QUALIFIED/DISQUALIFIED → ARCHIVED` (`SPAM` for abuse).
Status changes are review actions only and never provision anything.
