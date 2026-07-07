# SEO Metadata Readiness — Aish POS Lite

Sprint 21 — Public Website / Landing Page Readiness Foundation.

Verified by `SeoMetadataGovernanceService` and `public-website:readiness`.

## Page title template

`<Halaman> — Aish POS Lite` (e.g. "Paket & Harga — Aish POS Lite"). HOME and
PACKAGES must have a non-empty `seo_title`.

## Meta description template

150–160 chars, benefit-led, no unsupported claims. HOME and PACKAGES must have a
non-empty `seo_description`.

## Canonical placeholder

`<link rel="canonical" href="{current_url}">` is emitted by the public layout.

## Robots / sitemap readiness

- `<meta name="robots" content="index,follow">` in the layout.
- Sitemap generation is a readiness placeholder (documented, not yet automated).

## Open graph placeholder

`og:title`, `og:description`, `og:type` are emitted by the layout. Image/URL OG
tags are readiness placeholders.

## No deceptive SEO claims

No cloaking, no keyword stuffing, no claims that contradict the product foundation.
**No live external analytics/SEO token is embedded in Sprint 21.**
