#!/usr/bin/env bash
# UIX-8C-08 — font-scale reachability gate (DEF-007 / historical R18).
#
# WHY THIS EXISTS
# ---------------
# On physical hardware (Xiaomi 2311DRK48G, Android 14) the cashier surface became
# UNUSABLE at 130% system font: the search field, category filter, product list,
# cart, total and the "Bayar Tunai" checkout CTA were all pushed out of the
# viewport, and the root container does not scroll, so they were not
# scroll-reachable either.
#
# Root cause: the sync status/count labels were weighted (0dp + weight) children
# INSIDE the horizontal button rows. At large font the buttons grew and squeezed
# those labels to roughly one character wide, so they wrapped one character PER
# LINE and became tall enough to consume the viewport.
#
# This gate is fail-closed and has two modes:
#   static  (default, CI-safe)  — asserts the layout invariants that prevent the
#                                 regression, with no device required.
#   device                      — drives an attached PHYSICAL device at 100%,
#                                 115% and 130% font and asserts the checkout CTA
#                                 is actually present and inside the viewport.
#
# Rules: UIX8C-R021, UIX8C-R037/R038/R039, UIX8C-R041, UIX8C-R086, UIX8C-R088.
set -uo pipefail

MODE="${1:-static}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LAYOUT="$ROOT/android/app/src/main/res/layout/activity_cashier.xml"
FAILURES=0

pass() { printf '[PASS] %s\n' "$1"; }
bad()  { printf '[FAIL] %s\n' "$1"; FAILURES=$((FAILURES + 1)); }

# ---------------------------------------------------------------- static mode
static_gate() {
  echo "== UIX-8C-08 font reachability gate (static) =="
  [ -f "$LAYOUT" ] || { bad "cashier layout not found: $LAYOUT"; return; }

  # 1. Both sync labels must be height-capped so they can never grow unbounded
  #    when squeezed horizontally.
  for id in textSyncStatus textSyncCounts; do
    block=$(awk -v id="$id" '
      $0 ~ ("android:id=\"@\\+id/" id "\"") {found=1}
      found {print}
      found && /\/>/ {exit}
    ' "$LAYOUT")
    if [ -z "$block" ]; then
      bad "$id not found in cashier layout"
      continue
    fi
    if grep -q 'android:maxLines=' <<<"$block"; then
      pass "$id is height-capped (maxLines)"
    else
      bad "$id has no maxLines — it can wrap one character per line at large font and push the checkout CTA off-screen (DEF-007/R18)"
    fi
    if grep -q 'android:ellipsize=' <<<"$block"; then
      pass "$id ellipsizes instead of clipping"
    else
      bad "$id has no ellipsize"
    fi
  done

  # 2. The checkout CTA and the clear-cart control must live inside the bounded,
  #    scrollable action region so they stay reachable when space is tight.
  if grep -q 'androidx.core.widget.NestedScrollView' "$LAYOUT" &&
     grep -q 'android:id="@+id/cartActionScroll"' "$LAYOUT"; then
    pass "cart/action region is a bounded NestedScrollView"
  else
    bad "cart/action region is not a bounded NestedScrollView — the checkout CTA can become unreachable"
  fi
  if grep -q 'android:fillViewport="true"' "$LAYOUT"; then
    pass "action region uses fillViewport"
  else
    bad "action region missing fillViewport"
  fi

  # 3. The Settings entry point (DEF-005) must exist, or the guarded logout /
  #    cashier-switch flow is unreachable dead UI again.
  if grep -q 'android:id="@+id/buttonSettings"' "$LAYOUT"; then
    pass "Settings entry point present (DEF-005)"
  else
    bad "buttonSettings missing — Settings/logout becomes unreachable (DEF-005)"
  fi

  # 4. Guard the specific regression shape: a weighted TextView with no maxLines
  #    sitting inside a horizontal button row.
  if awk '
      /<TextView/ {buf=""; inblk=1}
      inblk {buf = buf $0 "\n"}
      inblk && /\/>/ {
        inblk=0
        if (buf ~ /android:layout_weight=/ && buf !~ /android:maxLines=/) { print "UNCAPPED"; }
      }
    ' "$LAYOUT" | grep -q UNCAPPED; then
    bad "a weighted TextView without maxLines exists in the cashier layout (DEF-007 regression shape)"
  else
    pass "no uncapped weighted TextView in the cashier layout"
  fi
}

# ---------------------------------------------------------------- device mode
device_gate() {
  echo "== UIX-8C-08 font reachability gate (device) =="
  local serial="${UIX8C08_SERIAL:-}"
  local adb="adb"
  [ -n "$serial" ] && adb="adb -s $serial"

  local devline
  devline="$($adb devices | awk 'NR>1 && $2=="device" {print $1}' | head -1)"
  if [ -z "$devline" ]; then bad "no attached device"; return; fi
  case "$devline" in
    emulator-*) bad "attached target is an EMULATOR ($devline); this gate requires a physical device (UIX7-R073/R075)"; return;;
  esac
  pass "physical device attached: ${devline:0:4}… (serial redacted)"

  local size h
  size="$($adb shell wm size | tr -d '\r' | awk -F': ' '{print $2}' | tail -1)"
  h="${size#*x}"
  [ -z "$h" ] && { bad "could not read screen size"; return; }

  local original
  original="$($adb shell settings get system font_scale | tr -d '\r')"
  [ "$original" = "null" ] && original="1.0"

  for scale in 1.0 1.15 1.30; do
    $adb shell settings put system font_scale "$scale" >/dev/null 2>&1
    $adb shell am force-stop com.aishtech.poslite >/dev/null 2>&1
    $adb shell am start -n com.aishtech.poslite/.MainActivity >/dev/null 2>&1
    sleep 9
    $adb shell uiautomator dump /sdcard/uix8c08_probe.xml >/dev/null 2>&1
    local dump bounds top bottom
    dump="$($adb shell cat /sdcard/uix8c08_probe.xml 2>/dev/null)"

    # Extract the checkout CTA node and its bounds.
    bounds="$(printf '%s' "$dump" | tr '<' '\n' \
      | grep 'resource-id="com.aishtech.poslite:id/buttonCheckout"' \
      | grep -oE 'bounds="\[[0-9]+,[0-9]+\]\[[0-9]+,[0-9]+\]"' | head -1)"

    if [ -z "$bounds" ]; then
      bad "font ${scale}: checkout CTA (buttonCheckout) NOT present in the view hierarchy"
      continue
    fi
    top="$(sed -E 's/.*\[[0-9]+,([0-9]+)\]\[[0-9]+,[0-9]+\].*/\1/' <<<"$bounds")"
    bottom="$(sed -E 's/.*\[[0-9]+,[0-9]+\]\[[0-9]+,([0-9]+)\].*/\1/' <<<"$bounds")"

    if [ "$bottom" -le "$top" ]; then
      bad "font ${scale}: checkout CTA has zero height ($bounds)"
    elif [ "$top" -ge "$h" ]; then
      bad "font ${scale}: checkout CTA starts below the viewport (top=$top, screen=$h) — not reachable (R18)"
    elif [ "$bottom" -gt "$h" ]; then
      bad "font ${scale}: checkout CTA is clipped by the viewport (bottom=$bottom, screen=$h)"
    else
      pass "font ${scale}: checkout CTA fully within viewport ($bounds, screen h=$h)"
    fi
  done

  $adb shell settings put system font_scale "$original" >/dev/null 2>&1
  echo "font_scale restored to $original"
}

case "$MODE" in
  static) static_gate;;
  device) device_gate;;
  both)   static_gate; device_gate;;
  *) echo "usage: $0 [static|device|both]"; exit 2;;
esac

echo
if [ "$FAILURES" -gt 0 ]; then
  echo "UIX-8C-08 FONT REACHABILITY GATE: FAIL ($FAILURES failure(s), mode=$MODE)"
  exit 1
fi
echo "UIX-8C-08 FONT REACHABILITY GATE: PASS (mode=$MODE)"
