#!/usr/bin/env bash
#
# Regression tests for scripts/uix8c_offline_cash_durability_gate.sh (UIX-8C-04).
# Proves the offline-CASH durability gate is fail-closed: it passes on the real
# tree and rejects (1) a missing rule, (2) a catch-all offline fallback, (3) QRIS
# offline, (4) a cart clear inside the repository (clear-before-durable-save),
# (5) a missing stable clientReference, (6) a missing bounded retry, (7) a missing
# idempotency test, (8) mutated historical failed-run evidence, (9) a premature
# UIX-7/UIX-8 GO tag, and (10) a secret value in touched source/evidence.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"; cd "$ROOT"
GATE=scripts/uix8c_offline_cash_durability_gate.sh
fails=0
ok(){ printf '  [ok]   %s\n' "$1"; }
no(){ printf '  [BAD]  %s\n' "$1"; fails=1; }

echo "== uix8c offline cash durability gate regression =="

JAVA="android/app/src/main/java/com/aishtech/poslite"
VM="$JAVA/feature/cashier/CashierViewModel.kt"
OFFLINE="$JAVA/data/repository/OfflineSaleRepository.kt"
RULE=".claude/rules/61-android-cashier-full-premium-delivery-foundation.md"
FRUN="docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json"
BTEST="backend/tests/Feature/OfflineCashDurabilityIdempotencyTest.php"
THREAT="docs/security/uix-8c-04-offline-cash-threat-model.md"

# 0. Real repo -> PASS.
bash "$GATE" >/dev/null 2>&1; RC=$?
[ "$RC" -eq 0 ] && ok "gate passes on the real tree" || no "gate should pass on the real tree (rc=$RC)"

mksandbox(){
  local d; d="$(mktemp -d)"
  mkdir -p "$d/android/app/src" "$d/.claude" "$d/scripts/tests" "$d/backend/tests/Feature"
  cp -r android/app/src/main "$d/android/app/src/"
  cp -r android/app/src/test "$d/android/app/src/"
  cp -r .claude/rules "$d/.claude/"
  cp -r docs "$d/"
  cp "$BTEST" "$d/$BTEST"
  cp "$GATE" "$d/scripts/"
  echo "$d"
}
runsb(){ ( cd "$1" && bash "$GATE" >/dev/null 2>&1 ); }

# 1. Clean sandbox -> PASS.
SB="$(mksandbox)"; runsb "$SB"; RC=$?
[ "$RC" -eq 0 ] && ok "gate passes on a clean sandbox mirror" || no "clean sandbox should pass (rc=$RC)"
rm -rf "$SB"

# 2. Drop a UIX-8C-04 rule id -> FAIL.
SB="$(mksandbox)"; sed -i 's/UIX8C-R098/UIX8C-RXXX/g' "$SB/$RULE"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a dropped rule id is rejected (UIX8C-R096..R130)" || no "dropped rule id must be rejected"
rm -rf "$SB"

# 3. Inject a catch-all offline fallback -> FAIL.
SB="$(mksandbox)"
sed -i '/catch (e: Exception) {/a\        saveOfflineFallback(emptyList(), 0L, "x", 0L)' "$SB/$VM"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a catch-all offline fallback is rejected (UIX8C-R103)" || no "catch-all fallback must be rejected"
rm -rf "$SB"

# 4. Enable QRIS on the offline path -> FAIL.
SB="$(mksandbox)"; printf '\n    // test: createQrisPayment(saleId)\n' >> "$SB/$OFFLINE"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "QRIS on the offline path is rejected (UIX8C-R096)" || no "QRIS offline must be rejected"
rm -rf "$SB"

# 5. Clear the cart inside the repository (clear-before-durable-save) -> FAIL.
SB="$(mksandbox)"; printf '\n    // test: cart.clear()\n' >> "$SB/$OFFLINE"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a cart.clear inside the repository is rejected (UIX8C-R107)" || no "repo cart.clear must be rejected"
rm -rf "$SB"

# 6. Remove the stable clientReference path -> FAIL.
SB="$(mksandbox)"; sed -i 's/checkoutReference/goneRef/g' "$SB/$VM"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a missing stable clientReference is rejected (UIX8C-R097)" || no "missing stable reference must be rejected"
rm -rf "$SB"

# 7. Remove the bounded retry cap -> FAIL.
SB="$(mksandbox)"; sed -i 's/MAX_SYNC_ATTEMPTS/GONE_CAP/g' "$SB/$OFFLINE"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a missing bounded retry cap is rejected (UIX8C-R115)" || no "missing bounded retry must be rejected"
rm -rf "$SB"

# 8. Remove the backend idempotency test -> FAIL.
SB="$(mksandbox)"; rm -f "$SB/$BTEST"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a missing backend idempotency test is rejected (UIX8C-R118)" || no "missing idempotency test must be rejected"
rm -rf "$SB"

# 9. Flip historical failed R11 FAIL -> PASS -> FAIL.
SB="$(mksandbox)"
python3 - "$SB" <<'PY'
import json,sys
p=sys.argv[1]+"/docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json"
d=json.load(open(p))
for f in d.get("findings",[]):
    if f.get("id")=="R11": f["status"]="PASS"
json.dump(d,open(p,"w"))
PY
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "flipping historical R11 to PASS is rejected (UIX8C-R129/R003)" || no "flipped R11 must be rejected"
rm -rf "$SB"

# 10. Premature UIX-7 GO tag -> FAIL.
SB="$(mksandbox)"
( cd "$SB" && git init -q && git -c user.email=t@t.co -c user.name=t commit --allow-empty -qm x \
  && git tag uix-7-android-cashier-experience-remediation-go ) >/dev/null 2>&1
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a premature UIX-7 GO tag is rejected (UIX8C-R130)" || no "premature UIX-7 GO tag must be rejected"
rm -rf "$SB"

# 11. Secret value in touched evidence -> FAIL.
SB="$(mksandbox)"; printf '\nAKIAABCDEFGHIJKLMNOP\n' >> "$SB/$THREAT"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a secret value in touched evidence is rejected (UIX8C-R127)" || no "secret value must be rejected"
rm -rf "$SB"

[ "$fails" -eq 0 ] && { echo "UIX-8C-04 OFFLINE CASH DURABILITY GATE TEST: PASS"; exit 0; } \
                   || { echo "UIX-8C-04 OFFLINE CASH DURABILITY GATE TEST: FAIL"; exit 1; }
