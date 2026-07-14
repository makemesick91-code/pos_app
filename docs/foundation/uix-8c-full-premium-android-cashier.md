# UIX-8C — Full Premium Android Cashier Governance & Foundation

This is the foundation narrative for the UIX-8C delivery train. The enforceable
rule set lives in `.claude/rules/61-android-cashier-full-premium-delivery-foundation.md`
(UIX8C-R001..R060) and is persisted in `docs/PROJECT_RULES.md`. This document
never overrides the modular rule; on any apparent conflict the modular rule is
authoritative.

## Purpose

UIX-8C is the **final premium Android delivery train** for genuine UIX-7/UIX-8
runtime closure of the native cashier (`com.aishtech.poslite`). It completes the
premium visual rebuild, truthful per-screen state, accessibility hardening, and
the physical-device closure campaign on top of UIX-8A (rule 56) and UIX-8B
(rule 57). It extends — never weakens — rules 55, 56, 57, 58, 59, 70/72, 80, 90.

## Current status (unchanged by UIX-8C-01)

- UIX-7: **NO-GO — GO DEFERRED**
- UIX-8: **IMPLEMENTATION COMPLETE — GO DEFERRED**

Immutable failed physical run (preserved verbatim, UIX8C-R003):
`docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json`

- `run-97fbb64-2af94aa` — runtime anchor `97fbb64`, repo HEAD `2af94aa`,
  pilot APK sha256 `1a83931…`, decision **NO_GO**.
- R01 **PENDING** (identity not visible), R11 **FAIL** (offline CASH not
  durable), R18 **FAIL** (layout collapse at 130% font). These are never edited
  into PASS (UIX8C-R003/R030).

## The 60 foundation rules (summary)

Delivery-train governance (R001–R005, R024–R025), screen/state foundation
(R006–R008, R023), financial/transaction integrity (R009–R016), visual &
accessibility (R017–R022), evidence/release discipline (R026–R030), and the
UIX-8C-02 premium design-system / responsive-shell / accessibility foundation
(R031–R060: visual system R031–R034, font-scale resilience R035–R041, layout
integrity R042–R044/R049, accessibility R045–R048, component & money
presentation R050–R055, evidence & sprint-tag discipline R056–R060). Full
authoritative text: `.claude/rules/61-android-cashier-full-premium-delivery-foundation.md`.

Key non-negotiables:

- UIX8C-R002 — no single **umbrella/final** UIX-8C GO tag; each implementation
  sprint MAY carry an immutable annotated **sprint-scoped** `uix-8c-NN-<slug>-go`
  tag that never asserts UIX-7/UIX-8 runtime closure (see UIX8C-R060).
- UIX8C-R003 — historical failed physical evidence is immutable.
- UIX8C-R009 — whole-Rupiah integer money is mandatory.
- UIX8C-R012/R013/R014 — governed offline CASH fallback; canonical HTTP
  rejection never becomes offline success; cart clears only after durable save.
- UIX8C-R016 — duplicate sale/payment/inventory mutation is automatic NO-GO.
- UIX8C-R030 — absence of evidence remains NO-GO.

## Scope of UIX-8C-01 (this sprint)

Governance + architecture + inventory + foundation only. It delivers:

1. The modular rule set UIX8C-R001..R030 (rule 61) and its persistence.
2. The dependency graph + full screen/state inventory
   (`docs/architecture/uix-8c-android-screen-state-architecture.md`).
3. The screen/state/accessibility matrix
   (`docs/testing/uix-8c-screen-state-accessibility-matrix.md`).
4. The delivery plan for UIX-8C-02..09
   (`docs/deployment/uix-8c-delivery-plan.md`).
5. The fail-closed foundation gate (`scripts/uix8c_foundation_gate.sh`) + tests
   (`scripts/tests/uix8c_foundation_gate_test.sh`), wired into CI.
6. ADR 0004 (`docs/adr/0004-uix-8c-full-premium-rebuild.md`).

It changes **no runtime code**, runs **no physical campaign**, modifies **no
runtime evidence/manifest**, builds **no closure APK**, and creates **no GO
tag** (scope guard, rule 61).

## Scope of UIX-8C-02 (design-system hardening)

UIX-8C-02 hardens the premium visual foundation the remaining screen sprints
build on. It delivers, **without** a runtime/financial behaviour change:

1. The permanent rule set UIX8C-R031..R060 (rule 61) and its persistence.
2. Centralized premium design tokens — colour/typography/spacing/shape/elevation/
   motion (`res/values/colors.xml|dimens.xml|styles.xml|themes.xml|shapes.xml`).
3. A reusable, accessible component library (`Widget.Aish.*`, `TextAppearance.Aish.*`,
   `component_state_*`, cashier context header) — token-driven, no per-screen copies.
4. A hardened **responsive cashier shell** that fixes the structural R18 large-font
   failure: the cashier and payment surfaces keep context, product, cart, total,
   and the checkout CTA visible or scroll-reachable at 100/115/130% font.
5. Design-system + font-scale + accessibility regression tests, and a fail-closed
   `scripts/uix8c_design_system_gate.sh` (+ self-tests) wired into CI.
6. ADR 0005 (`docs/adr/0005-uix-8c-02-premium-design-system-hardening.md`).

It does **not** fix R11, change `SaleService`/backend/Room semantics, alter
runtime evidence, run a physical campaign, or create a UIX-7/UIX-8 GO tag. It MAY
create the sprint-scoped tag `uix-8c-02-premium-design-system-hardening-go`
(UIX8C-R002/R060), which records only this sprint's implementation closure.

## How closure will happen

The implementation train (UIX-8C-02..09) remediates the screens and the failed
findings; each sprint is exact-SHA CI-gated (UIX8C-R027/R028) and MAY carry its
own immutable annotated **sprint-scoped** GO tag (UIX8C-R002). After code freeze
(UIX8C-R005/R024) a fresh APK (UIX8C-R004) drives the physical campaign; genuine
UIX-7/UIX-8 closure is recorded against the existing UIX-7/UIX-8 GO discipline
(rules 55/56/59/90) — UIX-8C mints **no umbrella/final** tag of its own
(UIX8C-R002), and no sprint tag asserts UIX-7/UIX-8 runtime closure (UIX8C-R060).
Absence of proof stays NO-GO (UIX8C-R030).
