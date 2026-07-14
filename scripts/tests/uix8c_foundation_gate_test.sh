#!/usr/bin/env bash
#
# Regression tests for scripts/uix8c_foundation_gate.sh (UIX-8C-01).
# Proves the gate is fail-closed: it passes on the real foundation, and fails
# when the failed physical run is flipped to PASS, when a rule id is dropped,
# when UIX-7/UIX-8 is declared GO, when a premature UIX-8C GO tag exists, or
# when a required doc is missing.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"; cd "$ROOT"
GATE=scripts/uix8c_foundation_gate.sh
fails=0
ok(){ printf '  [ok]   %s\n' "$1"; }
no(){ printf '  [BAD]  %s\n' "$1"; fails=1; }

echo "== uix8c foundation gate regression =="

# 0. Real repo -> PASS.
bash "$GATE" >/dev/null 2>&1; RC=$?
[ "$RC" -eq 0 ] && ok "gate passes on the real UIX-8C foundation" \
                || no "gate should pass on the real foundation (rc=$RC)"

# Build an isolated sandbox mirror the gate can run against (ROOT = sandbox).
FILES=(
  ".claude/rules/61-android-cashier-full-premium-delivery-foundation.md"
  "docs/PROJECT_RULES.md"
  "docs/foundation/uix-8c-full-premium-android-cashier.md"
  "docs/architecture/uix-8c-android-screen-state-architecture.md"
  "docs/testing/uix-8c-screen-state-accessibility-matrix.md"
  "docs/deployment/uix-8c-delivery-plan.md"
  "docs/adr/0004-uix-8c-full-premium-rebuild.md"
  "docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json"
  "docs/deployment/uix-7-runtime-evidence.json"
  "docs/deployment/uix-8-runtime-evidence.json"
  "scripts/uix8c_foundation_gate.sh"
)
mksandbox(){ # -> echoes sandbox dir
  local d; d="$(mktemp -d)"
  local f
  for f in "${FILES[@]}"; do
    mkdir -p "$d/$(dirname "$f")"; cp "$f" "$d/$f"
  done
  ( cd "$d" && git init -q && git add -A && git -c user.email=t@t -c user.name=t commit -qm init ) >/dev/null 2>&1
  echo "$d"
}
runsb(){ ( cd "$1" && bash scripts/uix8c_foundation_gate.sh >/dev/null 2>&1 ); }

# 1. Sandbox clean -> PASS.
SB="$(mksandbox)"; runsb "$SB"; RC=$?
[ "$RC" -eq 0 ] && ok "gate passes on a clean sandbox mirror" || no "clean sandbox should pass (rc=$RC)"
rm -rf "$SB"

# 2. Flip failed-run R11 FAIL -> PASS -> gate FAIL.
SB="$(mksandbox)"
python3 - "$SB" <<'PY'
import json,sys
p=sys.argv[1]+"/docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json"
d=json.load(open(p))
for f in d["findings"]:
    if f["id"]=="R11": f["status"]="PASS"
json.dump(d,open(p,"w"))
PY
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "flipping failed run R11 to PASS is rejected" || no "flipped failed-run must be rejected"
rm -rf "$SB"

# 3. Drop a rule id from PROJECT_RULES -> gate FAIL.
SB="$(mksandbox)"
sed -i 's/UIX8C-R015/UIX8C-RXXX/g' "$SB/docs/PROJECT_RULES.md"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a missing rule id is rejected" || no "missing rule id must be rejected"
rm -rf "$SB"

# 4. Declare UIX-8 GO in evidence -> gate FAIL.
SB="$(mksandbox)"
python3 - "$SB" <<'PY'
import json,sys
p=sys.argv[1]+"/docs/deployment/uix-8-runtime-evidence.json"
d=json.load(open(p)); d["decision"]="GO"; json.dump(d,open(p,"w"))
PY
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "declaring UIX-8 GO is rejected (must stay deferred)" || no "UIX-8 GO must be rejected"
rm -rf "$SB"

# 5. Premature UIX-8C GO tag -> gate FAIL.
SB="$(mksandbox)"
( cd "$SB" && git tag uix-8c-full-premium-android-cashier-go ) >/dev/null 2>&1
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a premature uix-8c-*-go tag is rejected" || no "premature GO tag must be rejected"
rm -rf "$SB"

# 6. Missing required doc -> gate FAIL.
SB="$(mksandbox)"
rm -f "$SB/docs/testing/uix-8c-screen-state-accessibility-matrix.md"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a missing required doc is rejected" || no "missing doc must be rejected"
rm -rf "$SB"

[ "$fails" -eq 0 ] && { echo "UIX-8C FOUNDATION GATE TEST: PASS"; exit 0; } \
                   || { echo "UIX-8C FOUNDATION GATE TEST: FAIL"; exit 1; }
