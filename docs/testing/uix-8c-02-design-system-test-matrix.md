# UIX-8C-02 — Design-System Test Matrix

Automated, fail-closed coverage for the premium design system, responsive shell,
and accessibility baseline (UIX8C-R031..R060). Development evidence only — never a
substitute for physical runtime closure (UIX8C-R056).

## JVM regression tests (`android/app/src/test/java/com/aishtech/poslite/`)

Run: `./gradlew :app:testDebugUnitTest` (also Pilot/Release). 143 tests, 0
failures.

| Test | Asserts | Rules |
|------|---------|-------|
| `DesignSystemResourceTest` | state colour tokens; spacing/shape/elevation tokens; money/status/receipt roles + `Widget.Aish.*` styles; Material 3 theme + centralized shapes; reusable state components exist; **no hardcoded hex**; **no raw dp/sp** design values in layouts. | R031–R035, R047, R050 |
| `FontScaleLayoutTest` | cashier product region weighted with `minHeight`; bottom action region is a `NestedScrollView` with `minHeight` (`cartActionScroll`); **both checkout CTAs inside the scroll region**; payment sheet root scrollable; no dp type sizes. | R037–R043 |
| `AccessibilityLayoutTest` | touch-target dimens ≥48dp; every interactive control has a style/`minHeight` (and any fixed height ≥48dp); cashier/payment controls carry content descriptions; status is text + colour (offline component, history row). | R044–R048 |

## Gate coverage (`scripts/uix8c_design_system_gate.sh`, fail-closed)

Token files present · Material 3 theme + shapes wired · canonical state colour
tokens · spacing/shape/elevation tokens · money/status/receipt roles +
component styles · reusable state components · **no hex / no raw dp / no raw sp**
in layouts · cashier shell scroll-bounded + CTA inside scroll · payment sheet
scrollable · offline status text+colour · font-scale/design tests present · failed
physical R18 stays FAIL · R060 sprint-tag non-closure clause persisted.

Self-test `scripts/tests/uix8c_design_system_gate_test.sh` proves fail-closed:
rejects injected hex, injected raw dp, a de-scrolled shell, a flipped R18, a
removed token, and a deleted component.

## Font-scale coverage

| Scale | Automated (this sprint) | Physical (deferred, operator) |
|-------|-------------------------|-------------------------------|
| 100% | structural invariant PASS | after code freeze (UIX8C-R059) |
| 115% | structural invariant PASS | after code freeze |
| 130% | structural invariant PASS (CTA scroll-reachable) | after code freeze — closes physical R18 |

Structure guarantees the CTA is scroll-reachable at every scale; the **visual**
PASS at each scale on a physical device is operator-performed and never fabricated.

## Build / lint

`assembleDebug`, `assemblePilot`, `assembleRelease` — BUILD SUCCESSFUL;
`lintDebug`/`lintPilot`/`lintRelease` — clean. Authoritative gate: the
`pull_request` CI (`_foundation-gates.yml` runs the design-system gate + its
self-tests; `_android-build.yml` builds/tests all variants).
