# UIX-1 — Handoff Audit & Manifest

**Source:** operator-provided UI/UX handoff package (read-only; not committed verbatim).
**Nature of the package:** a **design-foundation** package (tokens + specs + microcopy), **not** a set of
exported visual screens/prototypes. This scopes UIX-1 to *implementing and locking the foundation* into the
real app and *aligning existing surfaces*, plus documenting the spec'd-but-unbuilt screens as backlog.

## Manifest (7 files)

| Relative path | Type | Purpose | Implementation target | Status |
|---|---|---|---|---|
| `README.md` | Markdown | Handoff overview, product rules, a11y gate | Governance doc | Implemented |
| `DESIGN_TOKENS.json` | JSON | Cross-platform tokens (source of truth) | Android + web tokens | Implemented |
| `MICROCOPY.md` | Markdown | Official Indonesian terminology/labels | `strings.xml` `uix_*` | Implemented |
| `SCREEN_SPECS.md` | Markdown | Per-screen route/role/state/rules | Coverage matrix | Documented |
| `tokens/colors.xml` | Android XML | `res/values/colors.xml` (ready-to-paste) | `colors.xml` | Implemented |
| `tokens/dimens.xml` | Android XML | `res/values/dimens.xml` (ready-to-paste) | `dimens.xml` | Implemented |
| `tokens/aish-tokens.css` | CSS | Web console CSS variables | `aish-tokens.css` | Implemented |

**Assets:** no HTML/SVG/PNG/JPG/WebP/font binaries/animation files present. No third-party font files to
license-check; handoff itself directs Inter-with-system-fallback (see font substitution note, UIX-R020).

## What the handoff does NOT contain (and the honest consequence)

- No exported screen images/prototypes → visual-regression is against **spec + running UI**, not pixel diffs.
- No web-console (W1–W6) frontend code, and the repo has **no admin web console UI** (Sprints 11–36 are
  API + services only). W1–W6 cannot be "aligned" to an existing frontend because none exists — recorded as
  backlog / product-rule blocked, not fabricated.
- No net-new Android screen designs beyond specs → the ~30 spec'd screens that are not yet built remain
  backlog (`IMPLEMENTATION REQUIRED`); UIX-1 delivers the foundation they will consume.

## Integration surface actually present in the repo

- **Android** (`com.aishtech.poslite`, Kotlin XML Views, Material3): 7 screens — Login, Cashier(+item),
  QRIS, Receipt, Reports, Subscription/Device, MainActivity.
- **Web**: public-website Blade (home/packages/privacy/terms/thank-you) + `welcome`. No admin console UI.
