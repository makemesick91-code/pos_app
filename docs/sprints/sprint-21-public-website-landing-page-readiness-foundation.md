# Sprint 21 — Public Website / Landing Page Readiness Foundation

## Objective

Establish a content-governed, package-aligned, privacy-aware, lead-safe, SEO-aware,
secret-safe **public website / landing page readiness** foundation, gated before a
GO tag:

> Commercial Launch GO → Public Website Content Governance → Landing Page Shell →
> Lead Interest Governance → SEO/Privacy Readiness → Public Website Sign-off →
> Public Website GO/WATCH/NO-GO

This is a multi-tenant Android POS SaaS. Sprint 21 is public-website readiness — it
is **not** billing, **not** Play Store, **not** a real marketing campaign, and
**not** self-service onboarding automation.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`
- `docs/PROJECT_RULES.md`
- `docs/sprints/sprint-0..20-*.md`
- Sprint 20 commercial docs under `docs/commercial/`

## Previous Sprint Foundation Lock

Sprints 0–20 remain locked (see `docs/PROJECT_RULES.md` → Foundation Lock Index).
No prior behavior is modified; the public website surface is additive.

## Scope

Additive backend + public web + docs + tests + CI. No Android feature UI added
(Android CI stays the authoritative build gate). No public self-service signup, no
real billing collection, no live analytics/ad pixel, no auto production deploy, no
secrets/APK/AAB/keystore committed.

## Graphify Summary

- Reused: Sprint 11 platform admin + `AdminAuditLog`/`AdminAuditLogger`, Sprint 20
  `SaasPackageCatalog` (package alignment), cumulative Sprint 13–20 gate commands,
  `scripts/android_release_readiness.sh`.
- Added: `public_website_*` + `landing_page_versions` + `lead_interest_submissions`
  tables; `App\Services\PublicWebsite\*` (7 services); public routes/controllers/
  views; admin APIs behind `platform.admin`; 4 Artisan commands; `public_website`
  config; docs; tests; `sprint21-ci.yml`.

## Database Implementation

Migrations (`backend/database/migrations/2026_07_08_9100*`):

- `public_website_pages` — page_key/slug/status/SEO/content/approval.
- `landing_page_versions` — versioned landing content, CTA, highlights, approval.
- `lead_interest_submissions` — interest-only leads + consent.
- `public_website_signoffs` — preserved signoff records.
- `public_website_risks` — risk register + accepted-risk governance.

## Models and Relationships

`PublicWebsitePage`, `LandingPageVersion`, `LeadInterestSubmission`,
`PublicWebsiteSignoff`, `PublicWebsiteRisk` — with status/severity/role/decision
constants and `approver`/`creator`/`owner`/`acceptedRiskBy` relations.

## Services (`App\Services\PublicWebsite`)

- **PublicWebsiteReadinessService** — aggregates pages/landing/lead/SEO/privacy/
  risk/signoff → GO/WATCH/NO_GO; owns page lifecycle + signoff recording.
- **LandingPageContentService** — landing version lifecycle; interest-only CTA
  validation; package alignment vs Sprint 20 catalog.
- **LeadInterestGovernanceService** — interest-only lead capture (consent required,
  never provisions), status changes, summary.
- **SeoMetadataGovernanceService** — SEO title/description + readiness placeholders.
- **PrivacyCookieReadinessService** — privacy/terms pages + cookie/consent docs.
- **PublicWebsiteRiskGovernanceService** — risk lifecycle + accepted-risk rules.
- **PublicWebsiteGoNoGoService** — prior gates + docs + Android script + readiness.

All free-text/metadata is redacted via `SanitizesPublicWebsiteText` (incl. analytics
token fragments).

## Public Routes and Views

`GET /`, `/packages`, `/privacy`, `/terms`, `/thank-you`, `POST /interest`
(rate-limited `public-interest`, consent-required). Views under
`resources/views/public-website/*` — mobile-first, inline CSS, no external CDN, no
live tracking. Public pages never provision tenant/user/subscription/device.

## Admin Public Website APIs (`platform.admin`)

Pages, landing versions, leads (read + status), risks (+accept-risk/close),
signoffs, and read-only readiness/content-summary/lead-summary/go-no-go. Every
mutation is audit-logged. Tenant users → 403; unauthenticated → 401.

## Artisan Commands

- `public-website:readiness`
- `public-website:content-summary`
- `public-website:lead-summary`
- `public-website:go-no-go`

All support `--json`/`--strict`, print no secrets, run no Gradle, never deploy/bill/
alert/provision.

## Landing Page Content Map

See [../public-website/landing-page-content-map.md](../public-website/landing-page-content-map.md)
and the eight companion docs under `docs/public-website/`.

## Validation

- Backend: `php artisan test` → **628 passed** (584 prior + 44 new).
- Commands run clean (`--json`); NO_GO on empty DB is correct.
- Prior gates all satisfied; commercial launch gate intact.
- Smoke: `bash scripts/sprint21_smoke.sh`.
- Android: `scripts/android_release_readiness.sh` + `sprint21-ci.yml`
  (`assembleDebug` + `testDebugUnitTest` on JDK 21) remain the build gate.

## No-Go Rules honored

No public self-service signup; no real billing; no live analytics/ad pixel; no auto
deploy; no real alert sending; no Android business-flow change; no secrets/APK/AAB/
keystore committed; open CRITICAL/HIGH risk without valid accepted risk = NO-GO;
rejected signoff = NO-GO.

## GO Tag

`sprint-21-public-website-landing-page-readiness-foundation-go`.
