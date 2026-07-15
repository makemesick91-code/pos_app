#!/usr/bin/env bash
#
# Regression tests for scripts/uix8c_receipt_history_printer_gate.sh (UIX-8C-06).
# Proves the receipt/history/printer gate is fail-closed: it passes on the real
# tree and rejects (1) a missing rule id, (2) a broken receipt identity binding,
# (3) an accepted stale receipt (removed stale-guard test), (4) a duplicated
# local/server history row (broken reconciler merge), (5) Float/Double receipt
# money, (6) a printer failure that can roll back a transaction (financial
# reference in the printer package), (7) reprint calling checkout, (8) a print
# marking the transaction SYNCED (OfflineSyncStatus in the printer package), (9) an
# unsafe Bluetooth SCAN permission, (10) an unbounded printer retry loop, (11)
# missing accessibility/font tests, (12) mutated historical failed-run evidence,
# (13) a premature UIX-8 GO tag, and (14) a secret value in touched evidence.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"; cd "$ROOT"
GATE=scripts/uix8c_receipt_history_printer_gate.sh
fails=0
ok(){ printf '  [ok]   %s\n' "$1"; }
no(){ printf '  [BAD]  %s\n' "$1"; fails=1; }

echo "== uix8c receipt / history / printer gate regression =="

JAVA="android/app/src/main/java/com/aishtech/poslite"
TEST="android/app/src/test/java/com/aishtech/poslite"
PROJECTION="$JAVA/feature/receipt/ReceiptProjection.kt"
RCPT_VM="$JAVA/feature/receipt/ReceiptViewModel.kt"
RECONCILER="$JAVA/feature/history/TransactionHistoryReconciler.kt"
COORD="$JAVA/feature/printer/PrinterCoordinator.kt"
PSTATE="$JAVA/feature/printer/PrinterState.kt"
BTCONN="$JAVA/feature/printer/BluetoothPrinterConnection.kt"
MANIFEST="android/app/src/main/AndroidManifest.xml"
VMTEST="$TEST/ReceiptViewModelTest.kt"
LAYOUTTEST="$TEST/ReceiptHistoryLayoutTest.kt"
RULE=".claude/rules/61-android-cashier-full-premium-delivery-foundation.md"
FRUN="docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json"
BTEST="backend/tests/Feature/Uix8c06ReceiptHistoryParityTest.php"
THREAT="docs/security/uix-8c-06-receipt-printer-threat-model.md"

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

# 2. Drop a UIX-8C-06 rule id -> FAIL.
SB="$(mksandbox)"; sed -i 's/UIX8C-R190/UIX8C-RXXX/g' "$SB/$RULE"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a dropped rule id is rejected (UIX8C-R171..R210)" || no "dropped rule id must be rejected"
rm -rf "$SB"

# 3. Break the receipt identity binding (stale-result guard) -> FAIL.
SB="$(mksandbox)"; sed -i 's/boundIdentity/xId/g' "$SB/$RCPT_VM"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a broken receipt identity binding is rejected (UIX8C-R173)" || no "broken identity binding must be rejected"
rm -rf "$SB"

# 4. Remove the stale-result test (an accepted stale receipt) -> FAIL.
SB="$(mksandbox)"; sed -i 's/staleData_identityMismatch/staleUnchecked/g' "$SB/$VMTEST"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a missing stale-result test is rejected (UIX8C-R173)" || no "missing stale-result test must be rejected"
rm -rf "$SB"

# 5. Break the reconciler merge (duplicate local/server history rows) -> FAIL.
SB="$(mksandbox)"; sed -i 's/mergeKey/dupKey/g' "$SB/$RECONCILER"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a broken local/server merge is rejected (UIX8C-R182)" || no "broken merge must be rejected"
rm -rf "$SB"

# 6. Float/Double receipt money -> FAIL.
SB="$(mksandbox)"; printf '\nval leak: Double = 0.0\n' >> "$SB/$PROJECTION"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "Float/Double receipt money is rejected (UIX8C-R179)" || no "float money must be rejected"
rm -rf "$SB"

# 7. A financial reference in the printer package (print failure can roll back) -> FAIL.
SB="$(mksandbox)"; printf '\n// leak: OfflineSaleRepository authority\n' >> "$SB/$PSTATE"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a financial reference in the printer package is rejected (UIX8C-R191)" || no "printer financial reference must be rejected"
rm -rf "$SB"

# 8. Reprint calling checkout -> FAIL.
SB="$(mksandbox)"; printf '\n// leak: checkoutCash()\n' >> "$SB/$COORD"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "reprint calling checkout is rejected (UIX8C-R193)" || no "reprint-checkout must be rejected"
rm -rf "$SB"

# 9. A print marking the transaction SYNCED (OfflineSyncStatus in printer) -> FAIL.
SB="$(mksandbox)"; printf '\n// leak: OfflineSyncStatus.SYNCED\n' >> "$SB/$COORD"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a print marking the transaction SYNCED is rejected (UIX8C-R191)" || no "print-marks-synced must be rejected"
rm -rf "$SB"

# 10. Unsafe Bluetooth SCAN permission -> FAIL.
SB="$(mksandbox)"
sed -i 's#<uses-permission android:name="android.permission.BLUETOOTH_CONNECT" />#<uses-permission android:name="android.permission.BLUETOOTH_CONNECT" />\n    <uses-permission android:name="android.permission.BLUETOOTH_SCAN" />#' "$SB/$MANIFEST"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "an unsafe BLUETOOTH_SCAN permission is rejected (UIX8C-R195)" || no "BLUETOOTH_SCAN must be rejected"
rm -rf "$SB"

# 11. Unbounded printer retry loop -> FAIL.
SB="$(mksandbox)"; printf '\nfun poison() { while (true) { } }\n' >> "$SB/$BTCONN"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "an unbounded printer retry loop is rejected (UIX8C-R200)" || no "unbounded loop must be rejected"
rm -rf "$SB"

# 12. Missing accessibility/font tests -> FAIL.
SB="$(mksandbox)"; rm -f "$SB/$LAYOUTTEST"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "missing accessibility/font tests are rejected (UIX8C-R202/R206)" || no "missing a11y/font tests must be rejected"
rm -rf "$SB"

# 13. Flip historical failed R11 FAIL -> PASS -> FAIL.
SB="$(mksandbox)"
python3 - "$SB/$FRUN" <<'PY'
import json,sys
p=sys.argv[1]
d=json.load(open(p))
for f in d.get("findings",[]):
    if f.get("id")=="R11": f["status"]="PASS"
json.dump(d,open(p,"w"))
PY
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "flipping historical R11 to PASS is rejected (UIX8C-R209/R003)" || no "flipped R11 must be rejected"
rm -rf "$SB"

# 14. Premature UIX-8 GO tag -> FAIL.
SB="$(mksandbox)"
( cd "$SB" && git init -q && git -c user.email=t@t.co -c user.name=t commit --allow-empty -qm x \
  && git tag uix-8-android-cashier-premium-visual-transaction-experience-go ) >/dev/null 2>&1
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a premature UIX-8 GO tag is rejected (UIX8C-R210)" || no "premature UIX-8 GO tag must be rejected"
rm -rf "$SB"

# 15. Secret value in touched evidence -> FAIL.
SB="$(mksandbox)"; printf '\nAKIAABCDEFGHIJKLMNOP\n' >> "$SB/$THREAT"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a secret value in touched evidence is rejected (UIX8C-R199/R127)" || no "secret value must be rejected"
rm -rf "$SB"

[ "$fails" -eq 0 ] && { echo "UIX-8C-06 RECEIPT / HISTORY / PRINTER GATE TEST: PASS"; exit 0; } \
                   || { echo "UIX-8C-06 RECEIPT / HISTORY / PRINTER GATE TEST: FAIL"; exit 1; }
