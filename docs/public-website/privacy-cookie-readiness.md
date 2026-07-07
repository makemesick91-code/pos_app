# Privacy / Cookie Readiness — Aish POS Lite

Sprint 21 — Public Website / Landing Page Readiness Foundation.

Verified by `PrivacyCookieReadinessService` and `public-website:readiness`.

## Privacy page placeholder

`/privacy` renders a readiness template (clearly marked as **not legal finality**).
The PRIVACY page record must be `APPROVED`/`PUBLISHED` for readiness GO.

## Terms page placeholder

`/terms` renders a readiness template. The TERMS page record must be
`APPROVED`/`PUBLISHED` for readiness GO.

## Cookie / analytics placeholder

The site sets **no third-party analytics token and no live ad pixel** in Sprint 21.
Any future cookie/analytics usage must be disclosed here and consented to before
activation.

## Lead consent handling

The public interest form requires an explicit consent checkbox. The consent
timestamp is stored (`consent_accepted_at`). A lead without consent is rejected by
`LeadInterestGovernanceService`. See
[lead-interest-policy.md](lead-interest-policy.md).

## No live analytics token in Sprint 21

`public_website.live_tracking_tokens_allowed = false`. Introducing a live analytics
or ad pixel token is a NO-GO trigger for the public website gate.
