#!/usr/bin/env bash
#
# Regression tests for scripts/uix8c_design_system_gate.sh (UIX-8C-02).
# Proves the design-system gate is fail-closed: it passes on the real design
# system, and fails when a hardcoded hex/dp value is injected into a layout,
# when the responsive shell is de-scrolled, when a token is removed, when the
# failed physical R18 is flipped to PASS, or when a component layout is deleted.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"; cd "$ROOT"
GATE=scripts/uix8c_design_system_gate.sh
fails=0
ok(){ printf '  [ok]   %s\n' "$1"; }
no(){ printf '  [BAD]  %s\n' "$1"; fails=1; }

echo "== uix8c design-system gate regression =="

# 0. Real repo -> PASS.
bash "$GATE" >/dev/null 2>&1; RC=$?
[ "$RC" -eq 0 ] && ok "gate passes on the real design system" \
                || no "gate should pass on the real design system (rc=$RC)"

RESDIRS="android/app/src/main/res/values android/app/src/main/res/layout"
TESTDIR="android/app/src/test/java/com/aishtech/poslite"
mksandbox(){
  local d; d="$(mktemp -d)"
  local sub
  for sub in $RESDIRS; do mkdir -p "$d/$(dirname "$sub")"; cp -r "$sub" "$d/$sub"; done
  mkdir -p "$d/$TESTDIR"
  cp "$TESTDIR/DesignSystemResourceTest.kt" "$TESTDIR/FontScaleLayoutTest.kt" \
     "$TESTDIR/AccessibilityLayoutTest.kt" "$d/$TESTDIR/" 2>/dev/null
  mkdir -p "$d/.claude/rules" "$d/docs/deployment" "$d/scripts/tests"
  cp .claude/rules/61-android-cashier-full-premium-delivery-foundation.md "$d/.claude/rules/"
  cp docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json "$d/docs/deployment/"
  cp "$GATE" "$d/scripts/"
  echo "$d"
}
runsb(){ ( cd "$1" && bash scripts/uix8c_design_system_gate.sh >/dev/null 2>&1 ); }

# 1. Clean sandbox -> PASS.
SB="$(mksandbox)"; runsb "$SB"; RC=$?
[ "$RC" -eq 0 ] && ok "gate passes on a clean sandbox mirror" || no "clean sandbox should pass (rc=$RC)"
rm -rf "$SB"

# 2. Inject a hardcoded hex colour into a layout -> FAIL.
SB="$(mksandbox)"
sed -i 's|@color/bg_default|#FF00FF|' \
  "$SB/android/app/src/main/res/layout/activity_cashier.xml"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "hardcoded hex colour is rejected" || no "hardcoded hex must be rejected"
rm -rf "$SB"

# 3. Inject a raw dp design value into a layout -> FAIL.
SB="$(mksandbox)"
sed -i '0,/android:padding="@dimen\/space_lg"/s//android:padding="200dp"/' \
  "$SB/android/app/src/main/res/layout/activity_cashier.xml"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "raw dp design value is rejected" || no "raw dp value must be rejected"
rm -rf "$SB"

# 4. De-scroll the cashier shell (remove NestedScrollView) -> FAIL (R18 regression).
SB="$(mksandbox)"
sed -i 's/androidx.core.widget.NestedScrollView/FrameLayout/g' \
  "$SB/android/app/src/main/res/layout/activity_cashier.xml"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "de-scrolled cashier shell is rejected (R18 regression)" \
               || no "R18 regression must be rejected"
rm -rf "$SB"

# 5. Flip failed physical R18 FAIL -> PASS -> FAIL.
SB="$(mksandbox)"
python3 - "$SB" <<'PY'
import json,sys
p=sys.argv[1]+"/docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json"
d=json.load(open(p))
for f in d["findings"]:
    if f["id"]=="R18": f["status"]="PASS"
json.dump(d,open(p,"w"))
PY
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "flipping failed R18 to PASS is rejected (UIX8C-R058)" \
               || no "flipped R18 must be rejected"
rm -rf "$SB"

# 6. Remove a canonical token -> FAIL.
SB="$(mksandbox)"
sed -i 's/name="state_offline_fg"/name="removed_token"/' \
  "$SB/android/app/src/main/res/values/colors.xml"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a removed colour token is rejected" || no "removed token must be rejected"
rm -rf "$SB"

# 7. Delete a reusable component layout -> FAIL.
SB="$(mksandbox)"
rm -f "$SB/android/app/src/main/res/layout/component_state_error.xml"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a missing component layout is rejected" || no "missing component must be rejected"
rm -rf "$SB"

[ "$fails" -eq 0 ] && { echo "UIX-8C-02 DESIGN-SYSTEM GATE TEST: PASS"; exit 0; } \
                   || { echo "UIX-8C-02 DESIGN-SYSTEM GATE TEST: FAIL"; exit 1; }
