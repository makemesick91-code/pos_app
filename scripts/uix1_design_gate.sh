#!/usr/bin/env bash
# Aish POS — UIX-1 design foundation gate.
# Enforces the design-system governance rules (UIX-R001..UIX-R022): token files present,
# no hardcoded hex in Android layouts/Kotlin, canonical microcopy present, rules documented.
# Exit non-zero on any violation. Safe to run locally and in CI.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

fail=0
pass() { printf '  [PASS] %s\n' "$1"; }
bad()  { printf '  [FAIL] %s\n' "$1"; fail=1; }

echo "== UIX-1 design foundation gate =="

# 1. Token resource files present
echo "-- token resources --"
for f in \
  android/app/src/main/res/values/colors.xml \
  android/app/src/main/res/values/dimens.xml \
  android/app/src/main/res/values/styles.xml \
  android/app/src/main/res/values/themes.xml \
  backend/resources/css/aish-tokens.css; do
  [ -f "$f" ] && pass "$f" || bad "missing $f"
done

# 2. No hardcoded hex colors in Android layouts (UIX-R001)
echo "-- no hardcoded hex in Android layouts (UIX-R001) --"
hits="$(grep -rnE '#[0-9A-Fa-f]{6,8}' android/app/src/main/res/layout/ 2>/dev/null || true)"
if [ -n "$hits" ]; then bad "hardcoded hex in layouts:"; echo "$hits"; else pass "layouts clean"; fi

# 3. No Color.parseColor("#...") in Kotlin (UIX-R001)
echo "-- no parseColor hex in Kotlin (UIX-R001) --"
khits="$(grep -rnE 'parseColor\("#' android/app/src/main/java/ 2>/dev/null || true)"
if [ -n "$khits" ]; then bad "parseColor hex in Kotlin:"; echo "$khits"; else pass "Kotlin clean"; fi

# 4. Canonical microcopy present (UIX-R007, R011, R012)
echo "-- canonical microcopy (UIX-R007/R011/R012) --"
STR=android/app/src/main/res/values/strings.xml
for key in uix_offline_banner uix_sync_saved_device uix_qris_waiting uix_receipt_offline_header uix_coming_soon; do
  grep -q "name=\"$key\"" "$STR" && pass "$key" || bad "missing microcopy $key"
done

# 5. Foundation tabular-figures present (UIX-R005)
echo "-- tabular figures (UIX-R005) --"
grep -q 'fontFeatureSettings">tnum' android/app/src/main/res/values/styles.xml && pass "android tnum" || bad "android tnum missing"
grep -q 'font-variant-numeric: tabular-nums' backend/resources/css/aish-tokens.css && pass "web aish-num" || bad "web aish-num missing"

# 6. Governance rules documented (UIX-R001..R022)
echo "-- rules documented (UIX-R001..R022) --"
DOC=docs/foundation/uix-1-design-system.md
RULES=docs/PROJECT_RULES.md
for i in $(seq -w 1 22); do
  grep -q "UIX-R0$i" "$DOC"   || bad "UIX-R0$i missing in $DOC"
  grep -q "UIX-R0$i" "$RULES" || bad "UIX-R0$i missing in $RULES"
done
[ "$fail" -eq 0 ] && pass "all UIX-R001..R022 documented"

# 7. Coverage matrix present (UIX-R021)
echo "-- coverage matrix (UIX-R021) --"
[ -f docs/uiux/uix-1-screen-coverage.md ] && pass "coverage matrix present" || bad "missing coverage matrix"

# 8. Old GO tags immutability reminder is documented (UIX-R022)
grep -q 'pilot-shared-vps-post-go-hardening-go' "$DOC" && pass "old GO tags referenced immutable" || bad "UIX-R022 old GO tags note missing"

echo
if [ "$fail" -ne 0 ]; then
  echo "UIX-1 DESIGN GATE: FAIL"
  exit 1
fi
echo "UIX-1 DESIGN GATE: PASS"
