# ADR 0005 — UIX-8C-02 Premium Design-System Hardening, Responsive Shell & Sprint-Tag Governance

- Status: Accepted
- Date: 2026-07-14
- Sprint: UIX-8C-02
- Supersedes: none
- Extends: ADR 0002 (UIX-8A premium visual/transaction foundation), ADR 0003
  (UIX-8B native premium screen rebuild), ADR 0004 (UIX-8C delivery train)
- Related rules: `.claude/rules/61-android-cashier-full-premium-delivery-foundation.md`
  (UIX8C-R001..R060), 55, 56, 57, 58, 59, 70/72, 80, 90

## Context

UIX-8C-01 established the governance/architecture/foundation for the final
premium Android delivery train, but shipped **no runtime code**. The genuine
physical run `run-97fbb64-2af94aa` recorded three real failures — R01 PENDING,
R11 FAIL (offline CASH durability), **R18 FAIL (layout collapse at 130% font)**.

Two structural facts shape this sprint:

1. **The UIX-1 design foundation is already clean.** `Theme.AishPosLite` extends
   Material 3, the `TextAppearance.Aish.*` / `Widget.Aish.*` families exist,
   colour/dimens tokens are semantic, and layouts carry zero hardcoded hex and
   zero sub-48dp touch targets. This is a *hardening* sprint, not a greenfield
   design-system build.
2. **R18 is a layout-structure defect, not a token defect.** The cashier
   (`activity_cashier.xml`) and the cash payment sheet (`view_payment_sheet.xml`)
   had **non-scrollable roots** with a single weighted product region and a tall
   fixed bottom stack (cart summary, total, paid input, checkout CTAs). At 130%
   font the fixed stack grows past the screen and pushes the checkout CTA
   off-screen with no way to scroll to it.

The sprint also needs a governance decision: UIX8C-R002 (from UIX-8C-01)
forbade *any* `uix-8c-*-go` tag, but each implementation sprint now needs an
auditable, immutable closure marker without minting a false UIX-7/UIX-8 GO.

## Decision

1. **Centralize and extend the premium design system** (single visual source of
   truth): add explicit transaction/sync **state colour tokens**
   (online/offline/pending/syncing/synced/failed/conflict/disabled) plus a
   refined gold accent, fill the spacing scale (`space_2xs`, `space_lg_plus`),
   add elevation and shell-min-height dimens, add a centralized `shapes.xml`
   (`ShapeAppearance.Aish.*`), add money/status/receipt text roles, and add
   reusable component styles (tertiary/icon buttons, `EditText`, status chip,
   section header, bottom action region, state container). The theme wires the
   Material 3 shape families and a governed bottom-sheet shape.

2. **Build a reusable, accessible component library**: `component_state_*`
   (loading/empty/error/offline) and a compact cashier context header, all
   token-driven and status-text-plus-colour (never colour alone).

3. **Adopt the responsive cashier shell to fix R18 structurally.** The cashier
   root becomes three zones: a compact fixed header, a **weighted product
   region** (RecyclerView, internal scroll, `minHeight`), and a **weighted,
   internally-scrollable bottom action region** (`NestedScrollView`,
   `minHeight`) holding the cart summary, total, paid input, and checkout CTAs.
   Because both flexible zones are `0dp`-weighted scroll containers, the vertical
   stack can never exceed the screen at any font scale, and the checkout CTA is
   always visible or scroll-reachable. The payment sheet root becomes a
   `NestedScrollView` so its confirm CTA can never drop below the sheet fold.
   All view IDs are preserved, so `CashierViewModel`/`PaymentSheetFragment`
   ViewBinding wiring is unchanged — no business-logic change.

4. **Refine UIX8C-R002 sprint-tag governance (adds UIX8C-R060).** UIX-8C has
   **no single umbrella or final GO tag**, but each implementation sprint MAY
   carry an immutable, annotated, **sprint-scoped** `uix-8c-NN-<slug>-go` tag
   once merged with authoritative exact-SHA CI green, local/origin/VPS
   exact-match, and its sprint gates PASS. A sprint tag records only that
   sprint's *implementation* closure and **never asserts UIX-7 or UIX-8 runtime
   closure**. The fail-closed `uix8c_foundation_gate.sh` was updated to permit
   `uix-8c-NN-*-go` while still forbidding the two UIX-7/UIX-8 closure tags and
   any umbrella/final `uix-8c-*-go` tag.

5. **Enforce with a dedicated fail-closed gate** `scripts/uix8c_design_system_gate.sh`
   (+ self-tests) validating tokens, components, no-hardcode, the R18 shell
   structure, status-not-colour-alone, font-scale test presence, and R18
   immutability; plus JVM resource/font-scale/accessibility regression tests.

## Consequences

- The design system is the enforced single source of truth; a hardcoded value or
  a de-scrolled cashier shell is a CI-blocking regression.
- R18's **structural** cause is closed; the automated/emulator evidence for
  large-font operability is development evidence only. The **physical** R18 row
  in `run-97fbb64-2af94aa` stays FAIL until a fresh post-freeze APK is validated
  on a physical device (UIX8C-R058/R059). This ADR does not close UIX-7 or UIX-8.
- The sprint may mint `uix-8c-02-premium-design-system-hardening-go`; UIX-7 stays
  `NO-GO — GO DEFERRED` and UIX-8 stays `IMPLEMENTATION COMPLETE — GO DEFERRED`.

## Alternatives considered

- **Force a smaller font scale / hide context or cart to fit** — rejected: forbidden
  by UIX8C-R036 and a truthfulness/accessibility regression.
- **Wrap the whole cashier in one ScrollView** — rejected: a RecyclerView inside a
  plain ScrollView breaks recycling and creates nested-scroll dead zones
  (UIX8C-R043). The dual weighted-scroll shell is the correct pattern.
- **A full per-screen rebuild now** — deferred to UIX-8C-03..09; this sprint is the
  shared foundation and the minimal structural R18 fix only.
- **Keep UIX8C-R002 as "no tag at all"** — rejected: an implementation sprint needs
  an auditable, immutable closure marker; a sprint-scoped tag provides it without
  fabricating UIX-7/UIX-8 closure.
