# UIX-1 — Implementation Evidence

**Workstream:** Aish POS UIX-1 — Complete Handoff Implementation & Design Foundation Lock
**Branch:** `feature/uix-1-complete-handoff-implementation` (off `origin/main` @ `bb0274a`)

## What was implemented (real, in-repo)

### Android foundation (`com.aishtech.poslite`, Kotlin XML Views)
- `res/values/colors.xml` — semantic color tokens (new).
- `res/values/dimens.xml` — spacing/radius/touch/type/motion tokens (new).
- `res/values/styles.xml` — typography (`TextAppearance.Aish.*`) + component styles (buttons incl. 52dp pay,
  inputs, card, badge, numeric/currency with `tnum`) (new).
- `res/values/themes.xml` — app theme rewired to tokens, light-first (Material3.Light), status bar brand-dark.
- `res/values/strings.xml` — canonical Indonesian microcopy (`uix_*`: offline banner, canonical QRIS/sync
  status labels, cash-short, 3-part sync-failure, subscription block, device-limit, closing replay, clear-cart
  confirm, offline-receipt header, "SEGERA HADIR").
- Migrated **all** legacy hardcoded colors to tokens: 10 layout hex refs → `@color/*`; `ProductListAdapter.kt`
  `Color.parseColor` → `ContextCompat.getColor(R.color.*)` (low-stock → `status_warning_fg` per handoff).

### Web foundation
- `backend/resources/css/aish-tokens.css` — canonical CSS custom properties (new, self-contained, CDN-free).
- `backend/resources/views/public-website/layout.blade.php` — palette re-pointed to foundation values
  (brand/ink/bg/card/line + status note/error), added `.aish-num`, button pressed state, reduced-motion.

### Rules lock & gates
- `docs/foundation/uix-1-design-system.md` — foundation + UIX-R001..R022 governance rules.
- `docs/PROJECT_RULES.md` — appended UIX-1 rule section (UIX-R001..R022).
- `scripts/uix1_design_gate.sh` — enforces token files present, **zero** hardcoded hex, canonical microcopy,
  tabular figures, rules documented, coverage matrix present, old GO tags immutable.
- `.github/workflows/uix1-ci.yml` — design gate + rules-present + backend full suite + Vite build + Android
  (JDK 21) unit tests + `assembleDebug`.

### Docs (`docs/uiux/`)
handoff-audit · screen-coverage (matrix) · design-foundation (token mapping) · component-mapping ·
accessibility · responsive-validation · visual-regression · this evidence · deployment evidence.

## Validation results (this workstation)

| Check | Result |
|---|---|
| UIX-1 design gate (`scripts/uix1_design_gate.sh`) | **PASS** (all sections) |
| Hardcoded hex in Android layouts / Kotlin | **0 / 0** |
| Android resource XML well-formedness (9 files) | **OK** |
| Every `@color/*` used in layouts resolves | **OK** (5/5) |
| Blade compile (`view:cache`) after layout edit | **OK** |
| `route:list` sanity | **OK** |
| Backend targeted tests (`--filter=PublicWebsite`) | **34 passed / 125 assertions** |
| Contrast spot-checks (6 pairs) | **AA pass** (see accessibility doc) |

### Deferred to CI / on-device (recorded, not skipped)
- Android build/unit tests: local build not possible (JDK 25 here; CI is the JDK-21 gate per repo convention).
- Vite `npm run build`: needs network for `npm ci` (unavailable in this sandbox); runs in CI. Public-website
  palette change is inline-Blade, independent of the Vite bundle.
- Backend **full** suite: runs in CI (`uix1-ci.yml`); this change touches no backend PHP/routes/migrations.

## Graphify before / after (isolated to UIX-1)

Measured by stashing UIX-1 work at `bb0274a`, rebuilding, then restoring:

| | Nodes | Edges | Communities |
|---|---|---|---|
| Before (bb0274a) | 13,525 | 21,819 | 1,455 |
| After (UIX-1) | 13,589 | 21,875 | ~1,491 |
| **Delta** | **+64** | **+56** | additive |

Additive foundation nodes (color/dimen/style resources, docs, rules) and references to them (reuse). **No new
Android activity/screen nodes and no new route nodes** → no orphan screens, no dead routes introduced.

## Product-rule guardrails preserved (not weakened)

Server-only QRIS/sync status (UIX-R011), offline-receipt labelling (UIX-R012), idempotent sync
(UIX-R013), backend-owned subscription/device decisions (UIX-R009), tenant isolation (UIX-R014), future-state
labelling (UIX-R015). No permission/entitlement/tenant-isolation weakening; no fake active buttons added.
