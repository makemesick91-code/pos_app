# UIX-4 — Tenant Owner Console UI/UX

## Design system
Reuses the UIX-1/2/3 foundation: `backend/resources/css/aish-tokens.css` inlined
build-free (no Node/Vite). White foundation, blue hierarchy, restrained gold
accent, consistent radius/shadow, `.aish-num` tabular figures for money. All
color/spacing/type via `--aish-*` tokens — zero hardcoded hex in owner views.

The owner console reads as a business operations console (business identity chip,
outlet/device/subscription/usage/operations navigation), distinct from the
platform-admin control center.

## Components
- Owner top bar (business context + owner email + logout).
- Responsive sidebar with off-canvas mobile navigation (`aria-expanded`,
  `aria-current="page"`, Escape/backdrop close).
- Business identity chip (tenant name + code).
- Metric cards, status badges (ok/warn/bad/neutral), filters, tables in
  `overflow-x` scroll containers, loading state (login), empty state,
  "Tidak tersedia" unavailable state, restricted state page, notice/alert banner.

## Accessibility
- Semantic HTML, labelled form controls, visible focus (`:focus-visible`), skip
  link, `aria-pressed` password toggle, `aria-live` error banner, keyboard
  operable, `prefers-reduced-motion` honored, WCAG-AA token contrast.
- `noindex, nofollow` + same-origin referrer on every page.

## Responsive
Verified across 360 / 390 / 412 / 768 / 1024 / 1280 / 1440 / 1920: no horizontal
overflow (`overflow-x: hidden` guard + scrollable table wrappers), sidebar grid
collapses to single column with off-canvas nav under 860px.

## Truthful UI
Every figure comes from a canonical service. Unavailable reads render
"Tidak tersedia" — never a fabricated zero. Suspended/archived tenants see their
authoritative status and billing but not business listings. Device token and
fingerprint hashes are never shown.
