#!/usr/bin/env bash
# UIX-8C-02 design-system gate (fail-closed).
#
# Enforces the permanent premium Android design-system baseline
# (UIX8C-R031..R060) on the native cashier app (com.aishtech.poslite):
#   * centralized Material 3 tokens (colors/dimens/styles/themes/shapes) present;
#   * canonical state / money / status text roles + component styles present;
#   * reusable state + context-header component layouts present;
#   * NO hardcoded off-system values in layouts (no hex; no dp/sp literals beyond
#     the 0dp/1dp weight+hairline allowlist);
#   * responsive cashier shell + payment sheet are scroll-bounded (R18 fix);
#   * status is text + colour, never colour-alone;
#   * font-scale + design-system regression tests are present;
#   * the failed physical run R18 stays FAIL (never flipped by visual work);
#   * sprint-tag governance persisted (UIX8C-R060: a sprint tag never implies
#     UIX-7/UIX-8 runtime GO).
# Fail-closed: any missing/ambiguous check fails the gate.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }

echo "== UIX-8C-02 design-system gate =="

RES="android/app/src/main/res/values"
LAYOUT="android/app/src/main/res/layout"
COLORS="$RES/colors.xml"; DIMENS="$RES/dimens.xml"; STYLES="$RES/styles.xml"
THEMES="$RES/themes.xml"; SHAPES="$RES/shapes.xml"
RULE=".claude/rules/61-android-cashier-full-premium-delivery-foundation.md"
FAILED_RUN="docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json"

need_file(){ [ -f "$1" ] && pass "present: $1" || bad "missing: $1"; }
need_grep(){ # file token label
  grep -q "$2" "$1" 2>/dev/null && pass "$3" || bad "$3 (missing '$2' in $1)"; }

# 1. Token files present.
for f in "$COLORS" "$DIMENS" "$STYLES" "$THEMES" "$SHAPES"; do need_file "$f"; done

# 2. Theme is Material 3.
need_grep "$THEMES" "Theme.Material3" "theme extends Material 3 (UIX8C-R031)"
need_grep "$THEMES" "shapeAppearanceSmallComponent" "centralized shape appearances wired (UIX8C-R032)"

# 3. Canonical colour state tokens (status never colour-alone still needs the
#    distinct semantic colours to exist).
for c in state_online_fg state_offline_fg state_pending_fg state_syncing_fg \
         state_synced_fg state_failed_fg state_conflict_fg state_disabled_fg accent_gold; do
  grep -q "name=\"$c\"" "$COLORS" 2>/dev/null || bad "missing colour token: $c"
done
[ "$fail" -eq 0 ] && pass "canonical state colour tokens present (UIX8C-R032/R047)" || true

# 4. Spacing / shape / elevation tokens.
for d in space_2xs space_lg_plus radius_pill elevation_raised \
         cashier_product_min_height cashier_action_region_min_height status_chip_min_height; do
  grep -q "name=\"$d\"" "$DIMENS" 2>/dev/null || bad "missing dimen token: $d"
done
for s in ShapeAppearance.Aish.SmallComponent ShapeAppearance.Aish.Pill \
         ShapeAppearance.Aish.BottomSheet; do
  grep -q "name=\"$s\"" "$SHAPES" 2>/dev/null || bad "missing shape token: $s"
done

# 5. Canonical text roles + component styles.
for st in TextAppearance.Aish.MoneyTotal TextAppearance.Aish.MoneySecondary \
          TextAppearance.Aish.Status TextAppearance.Aish.Receipt \
          Widget.Aish.Button.Tertiary Widget.Aish.Button.Icon Widget.Aish.EditText \
          Widget.Aish.StatusChip Widget.Aish.SectionHeader \
          Widget.Aish.BottomActionRegion Widget.Aish.StateContainer; do
  grep -q "name=\"$st\"" "$STYLES" 2>/dev/null || bad "missing component style: $st"
done

# 6. Reusable component layouts present (UIX8C-R050).
for l in component_state_loading component_state_empty component_state_error \
         component_state_offline component_cashier_context_header; do
  need_file "$LAYOUT/$l.xml"
done

# 7. NO hardcoded off-system values in layouts (UIX8C-R033/R018).
hex="$(grep -rlE '"#[0-9A-Fa-f]{3,8}"' "$LAYOUT" 2>/dev/null || true)"
[ -z "$hex" ] && pass "no hardcoded hex colours in layouts" || bad "hardcoded hex colour in: $hex"
# dp literals: only 0dp (weight/constraint) and 1dp (hairline) are allowed.
badp="$(grep -rnE '"[0-9]+(\.[0-9]+)?dp"' "$LAYOUT" 2>/dev/null | grep -vE '"0dp"|"1dp"' || true)"
[ -z "$badp" ] && pass "no raw dp design values in layouts (0dp/1dp allowlist)" \
  || bad "raw dp literal(s) in layouts: $badp"
# sp literals: type sizes must be tokenized (system-scalable).
basp="$(grep -rnE '"[0-9]+(\.[0-9]+)?sp"' "$LAYOUT" 2>/dev/null || true)"
[ -z "$basp" ] && pass "no raw sp type sizes in layouts (UIX8C-R035)" \
  || bad "raw sp literal(s) in layouts: $basp"

# 8. Responsive shell (R18 fix) — cashier + payment sheet are scroll-bounded and
#    the checkout CTA lives inside a scroll region.
CASHIER="$LAYOUT/activity_cashier.xml"; SHEET="$LAYOUT/view_payment_sheet.xml"
if [ -f "$CASHIER" ]; then
  grep -q "NestedScrollView" "$CASHIER" && grep -q "cartActionScroll" "$CASHIER" \
    && grep -q "cashier_action_region_min_height" "$CASHIER" \
    && grep -q "cashier_product_min_height" "$CASHIER" \
    && pass "cashier shell is scroll-bounded (product + action region weighted, R18)" \
    || bad "cashier shell missing scroll-bounded action region (R18 regression)"
  # The checkout CTA must be inside the NestedScrollView action region.
  awk '/NestedScrollView/{ins=1} /buttonCheckout"/{if(ins)found=1} END{exit(found?0:1)}' "$CASHIER" \
    && pass "checkout CTA is inside the scroll-reachable action region (UIX8C-R039)" \
    || bad "checkout CTA is not inside the scroll region (UIX8C-R039)"
else
  bad "missing $CASHIER"
fi
if [ -f "$SHEET" ]; then
  head -20 "$SHEET" | grep -q "NestedScrollView" \
    && pass "payment sheet root is scrollable (UIX8C-R040)" \
    || bad "payment sheet root is not a NestedScrollView (UIX8C-R040)"
else
  bad "missing $SHEET"
fi

# 9. Status is text + colour, never colour-alone (UIX8C-R047): the offline state
#    component pairs a chip colour with a text label.
OFF="$LAYOUT/component_state_offline.xml"
if [ -f "$OFF" ]; then
  grep -q "textStateOffline" "$OFF" && grep -q "state_offline_fg" "$OFF" \
    && pass "offline state pairs text with colour (UIX8C-R047)" \
    || bad "offline state relies on colour alone (UIX8C-R047)"
fi

# 10. Font-scale + design-system regression tests present.
TESTDIR="android/app/src/test/java/com/aishtech/poslite"
for t in DesignSystemResourceTest.kt FontScaleLayoutTest.kt AccessibilityLayoutTest.kt; do
  [ -f "$TESTDIR/$t" ] && pass "regression test present: $t" || bad "missing regression test: $t"
done

# 11. Failed physical R18 stays FAIL (visual work never flips it) — UIX8C-R058.
if [ -f "$FAILED_RUN" ]; then
  FR="$FAILED_RUN" python3 - <<'PY' || fail=1
import json,os,sys
d=json.load(open(os.environ["FR"]))
byid={f.get("id"):str(f.get("status","")).upper() for f in d.get("findings",[])}
if byid.get("R18")=="FAIL":
    print("  [PASS] failed physical R18 remains FAIL after visual remediation (UIX8C-R058)")
else:
    print("  [FAIL] R18 must remain FAIL (got %r)"%byid.get("R18")); sys.exit(1)
PY
else
  bad "missing failed physical run record: $FAILED_RUN"
fi

# 12. Sprint-tag governance persisted (UIX8C-R060): a sprint tag never implies
#     UIX-7/UIX-8 runtime GO.
grep -q "UIX8C-R060" "$RULE" 2>/dev/null \
  && grep -qi "never asserts UIX-7 or UIX-8 runtime closure" "$RULE" 2>/dev/null \
  && pass "UIX8C-R060 sprint-tag non-closure clause persisted" \
  || bad "UIX8C-R060 sprint-tag non-closure clause missing"

[ "$fail" -eq 0 ] || { echo "UIX-8C-02 DESIGN-SYSTEM GATE: FAIL"; exit 1; }
echo "UIX-8C-02 DESIGN-SYSTEM GATE: PASS"
