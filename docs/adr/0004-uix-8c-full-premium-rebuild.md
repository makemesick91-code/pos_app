# ADR 0004 — UIX-8C Full Premium Android Cashier Rebuild & Closure Train

- Status: Accepted
- Date: 2026-07-14
- Sprint: UIX-8C-01
- Supersedes: none
- Extends: ADR 0001 (authoritative emulator evidence), ADR 0002 (UIX-8A premium
  visual/transaction foundation), ADR 0003 (UIX-8B native premium screen rebuild)
- Related rules: `.claude/rules/61-android-cashier-full-premium-delivery-foundation.md`
  (UIX8C-R001..R030), 55, 56, 57, 58, 59, 70/72, 80, 90

## Context

The native cashier app (`com.aishtech.poslite`) has a design-system foundation
(UIX-8A, rule 56) and rebuilt screen surfaces (UIX-8B, rule 57), but UIX-7 and
UIX-8 remain unclosed:

- UIX-7: `NO-GO — GO DEFERRED`
- UIX-8: `IMPLEMENTATION COMPLETE — GO DEFERRED`

A genuine physical-device run `run-97fbb64-2af94aa` (runtime anchor `97fbb64`,
repo HEAD `2af94aa`, pilot APK sha256 `1a83931…`) produced real failures:

- **R01 PENDING** — authenticated tenant/outlet identity not visible.
- **R11 FAIL** — offline CASH sale not durably saved.
- **R18 FAIL** — layout collapse at 130% font.

These are genuine defects, not noise. They must be remediated across a
disciplined delivery train, and the failed evidence must remain immutable.

## Decision

Deliver UIX-8C as a governed **delivery train** rather than a single mega-PR:

1. **UIX-8C-01 (this sprint)** establishes the permanent foundation: rule set
   `UIX8C-R001..R030`, the screen inventory, the screen/state/accessibility
   matrix, the target architecture, the delivery plan (UIX-8C-02..09), a
   fail-closed foundation gate (`scripts/uix8c_foundation_gate.sh`) wired into
   CI, and this ADR. It changes **no runtime code**, runs **no physical
   campaign**, modifies **no runtime evidence/manifest**, and creates **no GO
   tag**.

2. **UIX-8C-02..09** implement the premium rebuild screen-by-screen with
   truthful state, integer-exact money, governed offline persistence (closing
   R11), and accessibility hardening (closing R18/R01), each merged only behind
   an authoritative exact-SHA CI (UIX8C-R027/R028).

3. **Physical closure** (UIX8C-R005/R024) runs only after code freeze against a
   frozen exact-SHA candidate with a fresh APK (UIX8C-R004); UIX-8C does not
   mint its own GO tag (UIX8C-R002) — closure is recorded against the existing
   UIX-7/UIX-8 GO discipline (rules 55/56/59/90).

## Consequences

- The failed physical run is preserved as an immutable record
  (`docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json`) and is never
  edited to PASS (UIX8C-R003/R030).
- Each train sprint is small, reviewable, and independently CI-gated; runtime
  changes invalidate prior APK evidence (UIX8C-R004).
- Business truth stays in canonical backend/Android services (UIX8C-R008); the
  rebuild is presentation/state only.
- The foundation gate makes regressions (missing rule IDs, missing inventory or
  matrix, flipped failed evidence, premature GO tag, leaked secrets) a
  blocking, fail-closed CI failure.

## Alternatives considered

- **Single large rebuild PR** — rejected: too large to review, couples visual
  and transaction-safety risk, and cannot be exact-SHA CI-gated per concern.
- **Fix R11/R18 first, defer governance** — rejected: without the foundation
  gate and immutable-evidence discipline, a later sprint could silently flip the
  failed run to PASS, violating rule 59 (UIX8BOPS) and rule 90.
- **New `uix-8c-*-go` tag** — rejected: closure belongs to the existing
  UIX-7/UIX-8 GO tags; a parallel tag would fracture release governance
  (UIX8C-R002/R029).
