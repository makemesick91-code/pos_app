#!/usr/bin/env bash
# UIX-8C foundation gate (fail-closed).
#
# Validates the permanent UIX-8C governance/architecture/foundation:
#   * rule set UIX8C-R001..R060 persisted (modular rule + PROJECT_RULES);
#   * foundation / architecture / matrix / delivery docs + ADRs present;
#   * full screen inventory + screen/state/accessibility matrix present;
#   * the immutable failed physical run run-97fbb64-2af94aa is recorded and its
#     genuine failures (R01 PENDING, R11 FAIL, R18 FAIL) are NOT flipped to PASS;
#   * UIX-7 and UIX-8 remain GO DEFERRED (evidence decision != GO);
#   * no UIX-7/UIX-8 closure tag and no UIX-8C umbrella/final `uix-8c-*-go` tag
#     exists — sprint-scoped `uix-8c-NN-<slug>-go` tags ARE permitted (UIX8C-R002);
#   * no secret/credential pattern in UIX-8C artifacts.
# Fail-closed: any missing/ambiguous check fails the gate. Absence of proof is
# NO-GO (UIX8C-R030).
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }

echo "== UIX-8C-01 foundation gate =="

RULE=".claude/rules/61-android-cashier-full-premium-delivery-foundation.md"
PROJECT_RULES="docs/PROJECT_RULES.md"
FOUNDATION="docs/foundation/uix-8c-full-premium-android-cashier.md"
ARCH="docs/architecture/uix-8c-android-screen-state-architecture.md"
MATRIX="docs/testing/uix-8c-screen-state-accessibility-matrix.md"
PLAN="docs/deployment/uix-8c-delivery-plan.md"
ADR="docs/adr/0004-uix-8c-full-premium-rebuild.md"
ADR2="docs/adr/0005-uix-8c-02-premium-design-system-hardening.md"
DESIGN_GATE="scripts/uix8c_design_system_gate.sh"
FAILED_RUN="docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json"
UIX7_EVID="docs/deployment/uix-7-runtime-evidence.json"
UIX8_EVID="docs/deployment/uix-8-runtime-evidence.json"

# 1. Rule file + all rule ids present (modular rule + PROJECT_RULES).
[ -f "$RULE" ] && pass "modular rule 61 present" || bad "missing $RULE"
[ -f "$PROJECT_RULES" ] && pass "PROJECT_RULES present" || bad "missing $PROJECT_RULES"
missing_ids=""
for i in $(seq -w 1 95); do
  id="UIX8C-R0$i"
  if grep -q "$id" "$RULE" 2>/dev/null && grep -q "$id" "$PROJECT_RULES" 2>/dev/null; then :; else
    missing_ids="$missing_ids $id"
  fi
done
[ -z "$missing_ids" ] && pass "UIX8C-R001..R095 persisted (rule + PROJECT_RULES)" \
  || bad "UIX8C rule ids not fully persisted:$missing_ids"

# 2. Foundation / architecture / matrix / delivery docs + ADRs present, and the
#    UIX-8C-02 design-system gate exists (design-system regression is a blocker).
for d in "$FOUNDATION" "$ARCH" "$MATRIX" "$PLAN" "$ADR" "$ADR2"; do
  [ -f "$d" ] && pass "doc present: $d" || bad "missing doc: $d"
done
[ -f "$DESIGN_GATE" ] && pass "design-system gate present" || bad "missing $DESIGN_GATE"

# 3. Full screen inventory complete (architecture doc).
inv_missing=""
for s in "Splash" "Activation" "Login" "Expired session" "Activation failure" \
         "Device unavailable" "Logout / account switch" "Context header" "Products" \
         "Search" "Categories" "Empty catalog" "No-match" "Cached / offline catalog" \
         "Cash payment sheet" "Quick tender" "Manual tender" "Insufficient cash" \
         "Submitting" "Online success" "Offline queued" "Canonical server rejection" \
         "Pending" "Syncing" "Synced" "Retrying" "Failed" "Conflict" "Reconnect" \
         "Orphan-SYNCING recovery" "Current receipt" "Offline receipt" "Synced receipt" \
         "Transaction history" "Empty history" "Pending history" "Failed history" \
         "Transaction detail" "Cashier identity" "Tenant / outlet" "Device status" \
         "App version" "Network / sync status" "Printer status"; do
  grep -qF "$s" "$ARCH" 2>/dev/null || inv_missing="$inv_missing | $s"
done
[ -z "$inv_missing" ] && pass "screen inventory complete" || bad "screen inventory incomplete:$inv_missing"
# Dependency graph present in the architecture doc.
grep -q "Dependency graph" "$ARCH" 2>/dev/null && grep -q "backend idempotency" "$ARCH" 2>/dev/null \
  && pass "dependency graph documented" || bad "dependency graph missing in architecture doc"

# 4. State matrix complete (5 states + 7 accessibility gates + group coverage).
mtx_missing=""
for st in "loading" "empty" "error" "offline" "success"; do
  grep -qi "$st" "$MATRIX" 2>/dev/null || mtx_missing="$mtx_missing $st"
done
for a in "TalkBack" "Focus order" "Touch target" "colour" ; do
  grep -qi "$a" "$MATRIX" 2>/dev/null || mtx_missing="$mtx_missing $a"
done
for g in "Auth" "Cashier" "Cart" "Payment" "Sync" "Receipt" "History" "Settings"; do
  grep -q "| $g " "$MATRIX" 2>/dev/null || mtx_missing="$mtx_missing group:$g"
done
[ -z "$mtx_missing" ] && pass "state/accessibility matrix complete" || bad "matrix incomplete:$mtx_missing"

# 5. Immutable failed physical run recorded and NOT flipped to PASS.
if [ -f "$FAILED_RUN" ]; then
  pass "failed physical run record present"
  grep -q "run-97fbb64-2af94aa" "$FAILED_RUN" && pass "run id recorded" || bad "run id missing in failed run record"
  # Genuine failures must remain non-PASS. Use python to parse rows precisely.
  FR="$FAILED_RUN" python3 - <<'PY' || fail=1
import json,os,sys
p=os.environ["FR"]
try:
    d=json.load(open(p))
except Exception as e:
    print("  [FAIL] failed run record is not valid JSON: %s"%e); sys.exit(1)
ok=True
dec=str(d.get("decision","")).upper()
if dec=="GO":
    print("  [FAIL] failed run decision must never be GO"); ok=False
else:
    print("  [PASS] failed run decision is not GO (%s)"%dec)
want={"R01":"PENDING","R11":"FAIL","R18":"FAIL"}
byid={f.get("id"):str(f.get("status","")).upper() for f in d.get("findings",[])}
for rid,st in want.items():
    got=byid.get(rid)
    if got!=st:
        print("  [FAIL] finding %s must stay %s (got %r)"%(rid,st,got)); ok=False
    else:
        print("  [PASS] finding %s preserved as %s"%(rid,st))
# no finding may be PASS
for f in d.get("findings",[]):
    if str(f.get("status","")).upper()=="PASS":
        print("  [FAIL] a failed-run finding was flipped to PASS: %s"%f.get("id")); ok=False
sys.exit(0 if ok else 1)
PY
else
  bad "missing failed physical run record: $FAILED_RUN"
fi

# 6. UIX-7 and UIX-8 remain GO DEFERRED (evidence decision != GO).
for pair in "UIX-7:$UIX7_EVID" "UIX-8:$UIX8_EVID"; do
  label="${pair%%:*}"; f="${pair#*:}"
  if [ -f "$f" ]; then
    dec="$(F="$f" python3 -c 'import json,os;print(str(json.load(open(os.environ["F"])).get("decision","")).upper())' 2>/dev/null)"
    if [ "$dec" = "GO" ]; then bad "$label evidence decision is GO — must stay DEFERRED"; else pass "$label still deferred (decision=$dec)"; fi
  else
    bad "missing $label runtime evidence: $f"
  fi
done
# Foundation doc states the deferred posture explicitly.
grep -q "GO DEFERRED" "$FOUNDATION" 2>/dev/null && pass "foundation doc records GO DEFERRED status" || bad "foundation doc missing GO DEFERRED status"

# 7. No premature UIX-7/UIX-8 closure tag and no UIX-8C umbrella/final GO tag.
#    Sprint-scoped implementation tags `uix-8c-NN-<slug>-go` ARE permitted
#    (UIX8C-R002 refined by UIX8C-R060): a sprint tag never asserts UIX-7/UIX-8
#    runtime closure, so it is not premature.
tags="$(git tag 2>/dev/null)"
prem=""
for t in "uix-7-android-cashier-experience-remediation-go" \
         "uix-8-android-cashier-premium-visual-transaction-experience-go"; do
  printf '%s\n' "$tags" | grep -qx "$t" && prem="$prem $t"
done
while IFS= read -r t; do
  [ -z "$t" ] && continue
  case "$t" in
    uix-8c-[0-9][0-9]-*-go) : ;;            # sprint-scoped implementation GO tag — allowed
    uix-8c-*-go) prem="$prem $t" ;;         # umbrella/final UIX-8C closure tag — forbidden
  esac
done <<EOF
$tags
EOF
[ -z "$prem" ] && pass "no premature/umbrella GO tag (uix-7/uix-8/uix-8c-umbrella absent; sprint-scoped allowed)" \
  || bad "premature/umbrella GO tag present:$prem"
# Rule refines R002: no single umbrella/final tag, but sprint-scoped tags allowed,
# and a sprint tag never asserts UIX-7/UIX-8 runtime closure.
grep -q "UIX8C-R002" "$RULE" 2>/dev/null \
  && grep -qi "no single umbrella or final GO tag" "$RULE" 2>/dev/null \
  && grep -qi "sprint-scoped" "$RULE" 2>/dev/null \
  && grep -qi "never asserts UIX-7 or UIX-8 runtime closure" "$RULE" 2>/dev/null \
  && pass "UIX8C-R002 refined: umbrella/final forbidden, sprint-scoped allowed, non-closure" \
  || bad "UIX8C-R002 refined sprint-tag governance clause missing"

# 8. No secret/credential pattern in UIX-8C artifacts.
sec=0
for f in "$RULE" "$FOUNDATION" "$ARCH" "$MATRIX" "$PLAN" "$ADR" "$ADR2" "$FAILED_RUN" \
         scripts/uix8c_foundation_gate.sh scripts/tests/uix8c_foundation_gate_test.sh \
         "$DESIGN_GATE" scripts/tests/uix8c_design_system_gate_test.sh; do
  [ -f "$f" ] || continue
  if grep -nEi "(-----BEGIN [A-Z ]*PRIVATE KEY|AKIA[0-9A-Z]{16}|eyJ[A-Za-z0-9_-]{20}|authorization:[[:space:]]*bearer[[:space:]]+[A-Za-z0-9._-]{8}|password[[:space:]]*[:=][[:space:]]*['\"][^'\"]{4})" "$f" >/dev/null 2>&1; then
    bad "possible secret/credential pattern in $f"; sec=1
  fi
done
[ "$sec" -eq 0 ] && pass "no secret/credential pattern in UIX-8C artifacts"

[ "$fail" -eq 0 ] || { echo "UIX-8C FOUNDATION GATE: FAIL"; exit 1; }
echo "UIX-8C FOUNDATION GATE: PASS"
