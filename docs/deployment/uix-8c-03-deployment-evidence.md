# UIX-8C-03 — Deployment & Runtime Evidence

**Sprint:** UIX-8C-03 — Premium Cashier Home, Product Catalog, Search, Category & Cart
**Surface:** Android cashier `com.aishtech.poslite` (native Views/XML + ViewBinding + LiveData)
**Baseline before sprint:** `f7eab9b` (local = origin = VPS)

## Honest status

- **Automated and development validation: PASS.** JVM unit + structural
  regression suite is green (173 tests, 0 failures), all fail-closed gates pass,
  lint is clean, and all three variants (`debug`/`pilot`/`release`) build.
- **Final physical catalog/cart and large-font validation remains MANDATORY
  after final code freeze.** This environment cannot run instrumented/on-device
  Android tests; large-font (100/115/130%) visual confirmation and TalkBack
  observation are operator/physical and are not asserted here.
- **R11 offline CASH durability remains UNRESOLVED and outside this sprint.**
- The immutable failed physical run `run-97fbb64-2af94aa` (R01 PENDING, R11 FAIL,
  R18 FAIL) is preserved verbatim and is **not** flipped to PASS by this sprint.
- **UIX-7:** NO-GO — GO DEFERRED. **UIX-8:** IMPLEMENTATION COMPLETE — GO DEFERRED.
  The sprint tag `uix-8c-03-premium-cashier-catalog-cart-go` records ONLY
  UIX-8C-03 implementation closure; it never asserts UIX-7/UIX-8 runtime GO.

## CI / merge / deploy record (filled at closure)

| Item | Value |
| --- | --- |
| Branch | `feature/uix-8c-03-premium-cashier-catalog-cart` |
| PR | _(filled at merge)_ |
| Candidate SHA | _(filled at merge)_ |
| Authoritative CI run | _(filled at merge — exact final SHA, SUCCESS)_ |
| Merge commit | _(filled at merge)_ |
| Local = Origin = VPS | _(filled at deploy)_ |

## VPS Aish stack (filled at deploy)

| Check | Result |
| --- | --- |
| VPS HEAD == origin/main | _pending_ |
| Worktree clean | _pending_ |
| HTTPS root 200 / `/health/live` 200 / `/health/ready` 200 | _pending_ |
| nginx / postgresql / php8.5-fpm / aish-pos-queue-worker active | _pending_ |
| `www-data:www-data` ownership; zero root-owned runtime files | _pending_ |

> Note: UIX-8C-03 changes are Android-only (no backend/Blade/migration/dependency
> change). The VPS Aish stack is unaffected by application behaviour; the deploy
> is a fast-forward source sync + verification only.

## DaengtisiaMS co-tenant non-regression (filled at deploy)

| Check | Result |
| --- | --- |
| DMS HEAD unchanged (`8b0bb6a`) | _pending_ |
| DMS worktree clean | _pending_ |
| php8.3-fpm / nginx / postgresql / daengtisiams-queue-worker active | _pending_ |
| Pre-existing logrotate status not newly regressed | _pending_ |

## Evidence discipline

- Emulator/JVM/structural evidence stays labelled as such and never as
  physical-device closure (UIX8C-R056/R059, rule 55 UIX7-R062/R071..R080).
- Absence of physical evidence is stated honestly; it is never fabricated
  (UIX8C-R030).
