# Public Website Content Governance — Aish POS Lite

Sprint 21 — Public Website / Landing Page Readiness Foundation.

## Content owner

Marketing/Product owner is accountable for public website content. Legal/Privacy
owner is accountable for privacy/terms/cookie readiness.

## Review process

1. Draft created via admin API (`DRAFT`).
2. Moved to `REVIEW`; content owner + relevant signoff roles review.
3. `APPROVED` by content owner (recorded on the page/version).
4. `PUBLISHED` only after approval and a clean public website readiness check.

## Approval status

Pages and landing versions carry a status:
`DRAFT → REVIEW → APPROVED → PUBLISHED` (`ARCHIVED`/`BLOCKED` as needed). A page
must be `APPROVED` or `PUBLISHED` to count toward readiness.

## Publish checklist

- [ ] Content approved by content owner.
- [ ] No unsupported claims; package/pricing aligned with Sprint 20 catalog.
- [ ] No secrets, no admin URLs, no live analytics/ad pixel token.
- [ ] SEO title/description present for HOME and PACKAGES.
- [ ] Privacy/terms/cookie readiness present.
- [ ] `php artisan public-website:readiness --strict` returns GO.

## Evidence reference

Each page/landing version stores an `evidence_reference`. The consolidated evidence
lives in `docs/sprints/sprint-21-public-website-landing-page-readiness-foundation.md`.

## Rollback / archive process

To roll back, `archive` the current published landing version (or page); the
previously published version is superseded automatically on the next publish.
Archived records are preserved for audit.
