#!/usr/bin/env bash
#
# Regression tests for scripts/uix8c_cashier_catalog_cart_gate.sh (UIX-8C-03).
# Proves the cashier/catalog/cart gate is fail-closed: it passes on the real
# tree, and fails when the context header include is removed, when a rule id is
# dropped, when the category filter row is removed, when the category adapter is
# deleted, when a required doc is missing, when the chip touch target is dropped,
# or when the failed physical R18 is flipped to PASS.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"; cd "$ROOT"
GATE=scripts/uix8c_cashier_catalog_cart_gate.sh
fails=0
ok(){ printf '  [ok]   %s\n' "$1"; }
no(){ printf '  [BAD]  %s\n' "$1"; fails=1; }

echo "== uix8c cashier/catalog/cart gate regression =="

# 0. Real repo -> PASS.
bash "$GATE" >/dev/null 2>&1; RC=$?
[ "$RC" -eq 0 ] && ok "gate passes on the real tree" \
                || no "gate should pass on the real tree (rc=$RC)"

L="android/app/src/main/res/layout"
CHIP="$L/item_category_chip.xml"
ACT="$L/activity_cashier.xml"
ADAPTER="android/app/src/main/java/com/aishtech/poslite/feature/cashier/CategoryFilterAdapter.kt"
RULE=".claude/rules/61-android-cashier-full-premium-delivery-foundation.md"
FRUN="docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json"
DOC="docs/uiux/uix-8c-03-premium-cart.md"

mksandbox(){
  local d; d="$(mktemp -d)"
  mkdir -p "$d/android/app/src" "$d/.claude" "$d/scripts/tests"
  cp -r android/app/src/main "$d/android/app/src/"
  cp -r android/app/src/test "$d/android/app/src/"
  cp -r .claude/rules "$d/.claude/"
  cp -r docs "$d/"
  cp "$GATE" "$d/scripts/"
  echo "$d"
}
runsb(){ ( cd "$1" && bash "$GATE" >/dev/null 2>&1 ); }

# 1. Clean sandbox -> PASS.
SB="$(mksandbox)"; runsb "$SB"; RC=$?
[ "$RC" -eq 0 ] && ok "gate passes on a clean sandbox mirror" || no "clean sandbox should pass (rc=$RC)"
rm -rf "$SB"

# 2. Remove the canonical context-header include -> FAIL.
SB="$(mksandbox)"
sed -i 's#@layout/component_cashier_context_header#@layout/gone#g' "$SB/$ACT"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "removed context header include is rejected (UIX8C-R061)" \
               || no "missing context header include must be rejected"
rm -rf "$SB"

# 3. Drop a UIX-8C-03 rule id -> FAIL.
SB="$(mksandbox)"
sed -i 's/UIX8C-R074/UIX8C-RXXX/g' "$SB/$RULE"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a dropped rule id is rejected (UIX8C-R061..R095)" \
               || no "dropped rule id must be rejected"
rm -rf "$SB"

# 4. Remove the category filter row -> FAIL.
SB="$(mksandbox)"
sed -i 's/@+id\/listCategories/@+id\/gone/g' "$SB/$ACT"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "removed category filter row is rejected (UIX8C-R074)" \
               || no "missing category filter row must be rejected"
rm -rf "$SB"

# 5. Delete the category adapter -> FAIL.
SB="$(mksandbox)"
rm -f "$SB/$ADAPTER"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a missing category adapter is rejected" || no "missing adapter must be rejected"
rm -rf "$SB"

# 6. Drop the chip touch-target token -> FAIL.
SB="$(mksandbox)"
sed -i 's/@dimen\/touch_target_min/@dimen\/tiny/g' "$SB/$CHIP"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a category chip under 48dp is rejected (UIX8C-R090)" \
               || no "sub-48dp chip must be rejected"
rm -rf "$SB"

# 7. Remove a required doc -> FAIL.
SB="$(mksandbox)"
rm -f "$SB/$DOC"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a missing required doc is rejected" || no "missing doc must be rejected"
rm -rf "$SB"

# 8. Flip failed physical R18 FAIL -> PASS -> FAIL.
SB="$(mksandbox)"
python3 - "$SB" <<'PY'
import json,sys
p=sys.argv[1]+"/docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json"
d=json.load(open(p))
for f in d.get("findings",[]):
    if f.get("id")=="R18": f["status"]="PASS"
json.dump(d,open(p,"w"))
PY
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "flipping failed R18 to PASS is rejected (UIX8C-R058/R003)" \
               || no "flipped R18 must be rejected"
rm -rf "$SB"

[ "$fails" -eq 0 ] && { echo "UIX-8C-03 CASHIER/CATALOG/CART GATE TEST: PASS"; exit 0; } \
                   || { echo "UIX-8C-03 CASHIER/CATALOG/CART GATE TEST: FAIL"; exit 1; }
