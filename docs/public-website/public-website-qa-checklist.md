# Public Website QA Checklist — Aish POS Lite

Sprint 21 — Public Website / Landing Page Readiness Foundation.

## Public routes checklist

- [ ] `GET /` renders (200), no auth required.
- [ ] `GET /packages` renders governed active packages only.
- [ ] `GET /privacy` and `GET /terms` render readiness templates.
- [ ] `GET /thank-you` renders.
- [ ] `POST /interest` stores an interest-only lead and redirects to `/thank-you`.

## Mobile responsiveness checklist

- [ ] Layout is mobile-first; viewport meta present.
- [ ] Hero, cards, and form scale on small screens (≤ 560px).
- [ ] No horizontal overflow.

## Accessibility basic checklist

- [ ] Semantic headings (`h1/h2/h3`), labeled form fields.
- [ ] Sufficient color contrast.
- [ ] Keyboard-usable form and links.

## Privacy / terms checklist

- [ ] Privacy/terms pages present and marked as readiness templates.
- [ ] Cookie/analytics stance disclosed; no live token.
- [ ] Lead consent required and stored.

## Lead form checklist

- [ ] Consent required; missing consent rejected.
- [ ] Email/phone/message length-validated.
- [ ] Rate-limited (`public-interest`).
- [ ] Never provisions tenant/user/subscription/device.

## Performance checklist

- [ ] No external CDN/script dependency; inline CSS only.
- [ ] No heavy animation; fast first paint.

## Security checklist

- [ ] No secrets in page content or metadata.
- [ ] No admin URLs exposed on public pages.
- [ ] CSRF protection on `POST /interest`.
- [ ] No live analytics/ad pixel token.
