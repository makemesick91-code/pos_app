#!/usr/bin/env bash
# Self-tests for scripts/uix8c08_font_reachability_gate.sh (static mode).
#
# A gate is only worth having if it actually FAILS on the regression it exists to
# catch. These tests rebuild the DEF-007 / R18 regression shapes in a scratch copy
# of the cashier layout and assert the gate rejects each one.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
GATE="$ROOT/scripts/uix8c08_font_reachability_gate.sh"
REAL_LAYOUT="$ROOT/android/app/src/main/res/layout/activity_cashier.xml"
FAILS=0

ok()   { printf '  [ok]   %s\n' "$1"; }
nope() { printf '  [FAIL] %s\n' "$1"; FAILS=$((FAILS + 1)); }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
mkdir -p "$WORK/android/app/src/main/res/layout" "$WORK/scripts"
cp "$GATE" "$WORK/scripts/"

run_gate() { ( cd "$WORK" && bash scripts/uix8c08_font_reachability_gate.sh static >/dev/null 2>&1 ); }

echo "== uix8c08_font_reachability_gate self-tests =="

# 1. The real layout must PASS.
cp "$REAL_LAYOUT" "$WORK/android/app/src/main/res/layout/activity_cashier.xml"
if run_gate; then ok "real cashier layout passes"; else nope "real cashier layout should pass"; fi

# 2. Missing layout must FAIL (fail-closed, not silently pass).
rm -f "$WORK/android/app/src/main/res/layout/activity_cashier.xml"
if run_gate; then nope "missing layout should fail"; else ok "missing layout fails closed"; fi
cp "$REAL_LAYOUT" "$WORK/android/app/src/main/res/layout/activity_cashier.xml"

# 3. THE DEF-007 REGRESSION: drop maxLines from a weighted sync label.
#    This is precisely what made the label wrap one character per line at 130%
#    and push the checkout CTA off-screen.
perl -0pi -e 's/(android:id="\@\+id\/textSyncCounts".*?)\n\s*android:maxLines="2"/$1/s' \
  "$WORK/android/app/src/main/res/layout/activity_cashier.xml"
if run_gate; then nope "uncapped weighted sync label should fail (DEF-007)"; else ok "uncapped weighted sync label fails (DEF-007)"; fi
cp "$REAL_LAYOUT" "$WORK/android/app/src/main/res/layout/activity_cashier.xml"

# 4. Removing the Settings entry point must FAIL (DEF-005 regression).
sed -i 's|android:id="@+id/buttonSettings"|android:id="@+id/buttonSettingsRemoved"|' \
  "$WORK/android/app/src/main/res/layout/activity_cashier.xml"
if run_gate; then nope "missing Settings entry point should fail (DEF-005)"; else ok "missing Settings entry point fails (DEF-005)"; fi
cp "$REAL_LAYOUT" "$WORK/android/app/src/main/res/layout/activity_cashier.xml"

# 5. Losing the bounded scrollable action region must FAIL.
sed -i 's|androidx.core.widget.NestedScrollView|android.widget.LinearLayout|g' \
  "$WORK/android/app/src/main/res/layout/activity_cashier.xml"
if run_gate; then nope "unbounded action region should fail"; else ok "unbounded action region fails"; fi
cp "$REAL_LAYOUT" "$WORK/android/app/src/main/res/layout/activity_cashier.xml"

# 6. Losing fillViewport must FAIL.
sed -i 's|android:fillViewport="true"||' "$WORK/android/app/src/main/res/layout/activity_cashier.xml"
if run_gate; then nope "missing fillViewport should fail"; else ok "missing fillViewport fails"; fi

echo
if [ "$FAILS" -gt 0 ]; then
  echo "uix8c08_font_reachability_gate self-tests: FAIL ($FAILS)"
  exit 1
fi
echo "uix8c08_font_reachability_gate self-tests: PASS"
