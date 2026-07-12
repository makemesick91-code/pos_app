# Aish POS — UIX-1 Design System & UI Governance (Foundation Lock)

**Workstream:** Aish POS UIX-1 — Complete Handoff Implementation & Design Foundation Lock
**Source of truth:** operator-provided UI/UX handoff package (`DESIGN_TOKENS.json`, `MICROCOPY.md`,
`SCREEN_SPECS.md`, `tokens/colors.xml`, `tokens/dimens.xml`, `tokens/aish-tokens.css`).

Once implemented in the app, the **implemented tokens/components are the source of truth**; the handoff
folder is design *input* only. Future visual changes must preserve compatibility and document deviations.

## Implemented foundation surfaces

| Platform | Location | Contents |
|---|---|---|
| Android | `android/app/src/main/res/values/colors.xml` | Semantic color tokens |
| Android | `android/app/src/main/res/values/dimens.xml` | Spacing / radius / touch / type / motion |
| Android | `android/app/src/main/res/values/styles.xml` | Typography + component styles |
| Android | `android/app/src/main/res/values/themes.xml` | App theme wired to tokens |
| Android | `android/app/src/main/res/values/strings.xml` | Canonical Indonesian microcopy (`uix_*`) |
| Web | `backend/resources/css/aish-tokens.css` | CSS custom properties (canonical) |
| Web | `backend/resources/views/public-website/layout.blade.php` | Palette aligned to foundation |

## Design tokens (canonical values)

- **Brand:** dark `#0B1020`, secondary `#6D5DFB`, support `#06B6D4`, premium `#D6A84B`
- **Action:** primary `#2563EB` / pressed `#1D4ED8`, success `#15803D`, destructive `#B91C1C`
- **Background/surface:** default `#F7F9FC`, subtle `#F1F4F9`, surface `#FFFFFF`
- **Text:** primary `#111827`, secondary `#64748B`, disabled `#9CA3AF`, on-dark `#FFFFFF`
- **Border:** default `#E2E8F0`, subtle `#EEF2F7`
- **Status:** success / warning / danger / info (fg + bg + border triplets)
- **Spacing:** 4·8·12·16·24·32·48 dp · **Radius:** input 8 / card 12 / sheet 16 / chip 999
- **Touch target:** min 48 dp, pay button 52 dp · **Motion:** 150 / 220 / 300 ms
- **Type:** Inter → Roboto/system fallback; tabular figures for all financial numbers

> **Font substitution note:** the handoff specifies Inter. Inter binaries are **not** committed
> (license review pending); the documented Roboto/system fallback is used. This is an intentional,
> recorded deviation (UIX-R020).

## Permanent UI governance rules

These rules are enforced by `scripts/uix1_design_gate.sh` (presence + hardcoded-hex scan) and CI
(`.github/workflows/uix1-ci.yml`). Rule wording follows the handoff's non-negotiable product rules.

- **UIX-R001** — Semantic color tokens only. No hardcoded hex in Android layouts/Kotlin or web components.
- **UIX-R002** — Typography via defined styles; body ≥ 14 sp; caption reserved for metadata.
- **UIX-R003** — Spacing uses the 4 dp scale tokens; no ad-hoc magic dp for structural spacing.
- **UIX-R004** — Touch target ≥ 48 dp; pay/confirm button 52 dp.
- **UIX-R005** — Financial/numeric text uses tabular figures (`tnum` / `.aish-num`).
- **UIX-R006** — Every feature screen provides loading, empty, and error states.
- **UIX-R007** — Offline-aware screens show offline/sync state using the canonical labels.
- **UIX-R008** — Permission enforced in navigation and actions; backend remains the source of truth.
- **UIX-R009** — Entitlement lock/upgrade states come from the backend decision; never computed client-side.
- **UIX-R010** — Destructive actions require confirmation; financial actions are server-confirmed only.
- **UIX-R011** — QRIS/sync never display "berhasil" before the backend confirms PAID / synced.
- **UIX-R012** — Offline receipts are labelled `*** STRUK OFFLINE / BELUM SYNC ***` until server confirms.
- **UIX-R013** — Idempotent sync via `client_reference`; failed items are not user-deletable; no duplicate UI transactions.
- **UIX-R014** — Tenant isolation absolute; no cross-tenant data in UI; sensitive admin actions need reason + typed confirmation + audit log.
- **UIX-R015** — A feature without backend support is a labelled "SEGERA HADIR" future-state — no active fake button.
- **UIX-R016** — Reuse foundation components before creating new ones; no duplicate components; variants are explicit.
- **UIX-R017** — Status is conveyed by icon + label, never color alone; contrast meets WCAG AA.
- **UIX-R018** — Motion ≤ 300 ms; respect `prefers-reduced-motion`; no continuous decorative animation.
- **UIX-R019** — Elevation via border (not heavy shadow/blur) for entry-level devices; optimized/vector assets; large lists lazy/virtualized.
- **UIX-R020** — The handoff folder is design input; implemented tokens/components are the app source of truth; deviations are documented.
- **UIX-R021** — New screens are registered in the UIX-1 coverage matrix (`docs/uiux/uix-1-screen-coverage.md`).
- **UIX-R022** — Existing deployment GO tags (`pilot-shared-vps-isolated-deployment-go`, `pilot-shared-vps-post-go-hardening-go`) are immutable; the UIX-1 GO tag is created only on verified evidence.

## Error message pattern (mandatory for every error state)

1. What happened (one sentence) → 2. Likely cause → 3. What you can do →
4. A specific action button ("Coba Sinkronkan Lagi", never "OK").

## Accessibility release gate

WCAG AA contrast · status = icon + label (never color alone) · touch target ≥ 48 dp ·
text survives 130% system font scale · error text anchored to its field · destructive actions
confirmed · motion ≤ 300 ms with `prefers-reduced-motion` respected.
