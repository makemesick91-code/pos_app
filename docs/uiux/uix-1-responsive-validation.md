# UIX-1 — Responsive Validation

## Scope & honesty

The handoff is a token/spec package with no exported screens, and this workstation cannot run the Android
emulator locally (JDK 25 present; the Android build/UI gate runs on CI at JDK 21 per repo convention). So
responsive validation is: (a) foundation correctness across breakpoints, (b) web responsiveness verified in
markup, (c) Android layout responsiveness deferred to CI build + on-device pilot smoke.

## Breakpoints (from tokens)

Android target phone widths 360 / 390 / 412 dp; tablet 600 / 800 / 1280 dp (T1–T4 backlog). Web fluid.

## Web (public-website) — verified in layout

- Fluid container `max-width:960px`, `padding:0 20px`.
- `grid` uses `repeat(auto-fit, minmax(220px,1fr))` — reflows on narrow screens.
- `@media (max-width:560px)` reduces hero title and nav spacing.
- `meta viewport` present; no fixed-pixel page width; images/content wrap.
- No horizontal page scroll introduced by the palette change (colors only; layout untouched).

## Android — status

- Foundation uses `dp`/`sp` and 48dp touch targets → density-independent.
- Existing 7 screens keep their layouts; only colors/strings were tokenized, so no regression risk to
  responsiveness. Tablet two-pane (T1) is backlog (`IMPLEMENTATION REQUIRED`).
- Full responsive screenshot validation across 360/390/412 + tablet is gated on CI `assembleDebug` and the
  pilot on-device smoke (deployment evidence).

## Deviation classification

| Item | Class |
|---|---|
| Font Inter→system | INTENTIONAL NATIVE ADAPTATION |
| Border elevation vs shadow | ACCESSIBILITY/PERF IMPROVEMENT |
| Web palette re-point | MATCHED (foundation) |
| Tablet two-pane | BLOCKED (backlog, not in this change) |
