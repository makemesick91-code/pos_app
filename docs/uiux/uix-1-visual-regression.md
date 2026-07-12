# UIX-1 — Visual Regression

## Method (and its honest limits)

The handoff contains **no exported screen images**, so there is no pixel baseline to diff against. Visual
validation is therefore: spec conformance + running-surface review, not automated pixel regression. No
emulator is available on this workstation; Android visual capture is deferred to CI/on-device pilot smoke.
No screenshots containing sensitive or real tenant/financial data are committed.

## What was validated

| Surface | Method | Result |
|---|---|---|
| Web public-website | Blade markup review of palette re-point; token values match `DESIGN_TOKENS.json` | MATCHED |
| Android tokens | `colors.xml`/`dimens.xml` byte-compared to handoff `tokens/*.xml` (same values) | MATCHED |
| Android theme wiring | `themes.xml`/`styles.xml` reference tokens only; no hex | MATCHED |
| Legacy hex migration | design gate: 0 hardcoded hex in layouts/Kotlin | MATCHED |

## Deviation classification

- **MATCHED:** token values, web palette.
- **INTENTIONAL NATIVE ADAPTATION:** Inter→system font; light-first theme.
- **ACCESSIBILITY/PERF IMPROVEMENT:** border elevation; low-stock red→warning-amber.
- **BLOCKED BY PRODUCT RULE:** admin web console (W1–W6) has no frontend; future-state features.

## Checks explicitly deferred (recorded, not skipped silently)

Clipping / overflow / keyboard overlap / status-bar overlap / broken icons on live Android — to be captured
during the pilot on-device smoke and CI build. This deferral is a consequence of no local emulator + no
exported baselines, not a reduction of the gate.
