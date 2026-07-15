#!/usr/bin/env bash
#
# UIX-8C-06 — fail-closed gate for the premium receipt, transaction-history, and
# printer failure-state baseline (UIX8C-R171..R210).
#
# It verifies (structurally, machine-checkable):
#   * rule set UIX8C-R171..R210 persisted (modular rule 61 + PROJECT_RULES);
#   * the required UIX-8C-06 docs exist;
#   * the pure presentation components exist (ReceiptProjection/ReceiptProjector,
#     TransactionHistoryReconciler, PrinterCoordinator, typed PrinterFailure/PrintResult);
#   * the receipt binds to a stable transaction identity (clientReference/serverSaleId);
#   * whole-Rupiah parity + no unsafe Float/Double on the money path;
#   * local/server history merge + one-row-per-logical-transaction tests;
#   * PENDING/SYNCING/SYNCED/FAILED/CONFLICT distinctions;
#   * the printer package has NO financial-transaction reference (non-financial authority);
#   * reprint does not call checkout/payment; retry is bounded; no BLUETOOTH_SCAN;
#   * stale-result, restoration, printer-authority, accessibility and 130%-font tests;
#   * the backend receipt/history parity regression fence;
#   * the immutable failed physical run stays FAIL; UIX-7/UIX-8 stay deferred;
#   * no premature UIX-7/UIX-8 GO tag; no secret value in touched source/docs.
#
# Absence/failure of proof = FAIL (fail-closed).
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }

echo "== UIX-8C-06 premium receipt / transaction-history / printer failure-state gate =="

JAVA="android/app/src/main/java/com/aishtech/poslite"
TEST="android/app/src/test/java/com/aishtech/poslite"
BTEST="backend/tests/Feature"
RULE=".claude/rules/61-android-cashier-full-premium-delivery-foundation.md"
PROJECT_RULES="docs/PROJECT_RULES.md"
FAILED_RUN="docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json"
UIX7_EVID="docs/deployment/uix-7-runtime-evidence.json"
UIX8_EVID="docs/deployment/uix-8-runtime-evidence.json"

PROJECTION="$JAVA/feature/receipt/ReceiptProjection.kt"
PROJECTOR="$JAVA/feature/receipt/ReceiptProjector.kt"
RCPT_VM="$JAVA/feature/receipt/ReceiptViewModel.kt"
RCPT_STATE="$JAVA/feature/receipt/ReceiptStateDisplay.kt"
HIST_MODELS="$JAVA/feature/history/TransactionHistoryModels.kt"
RECONCILER="$JAVA/feature/history/TransactionHistoryReconciler.kt"
HIST_VM="$JAVA/feature/history/TransactionHistoryViewModel.kt"
COORD="$JAVA/feature/printer/PrinterCoordinator.kt"
PSTATE="$JAVA/feature/printer/PrinterState.kt"
PCONN="$JAVA/feature/printer/PrinterConnection.kt"
BTCONN="$JAVA/feature/printer/BluetoothPrinterConnection.kt"
PRINTER_DIR="$JAVA/feature/printer"
MANIFEST="android/app/src/main/AndroidManifest.xml"
MONEY="$JAVA/core/money/RupiahMoney.kt"

need_file(){ [ -f "$1" ] && pass "present: $1" || bad "missing: $1"; }
need_grep(){ grep -q "$2" "$1" 2>/dev/null && pass "$3" || bad "$3 (missing '$2' in $1)"; }
deny_grep(){ grep -q "$2" "$1" 2>/dev/null && bad "$3 (found '$2' in $1)" || pass "$3"; }

# 1. Rules UIX8C-R171..R210 persisted in BOTH the modular rule and PROJECT_RULES.
missing_ids=""
for i in $(seq 171 210); do
  id="$(printf 'UIX8C-R%03d' "$i")"
  grep -q "$id" "$RULE" && grep -q "$id" "$PROJECT_RULES" || missing_ids="$missing_ids $id"
done
[ -z "$missing_ids" ] && pass "UIX8C-R171..R210 persisted (rule + PROJECT_RULES)" \
  || bad "UIX-8C-06 rule ids not fully persisted:$missing_ids"

# 2. Required UIX-8C-06 docs.
for d in \
  docs/adr/0008-uix-8c-06-receipt-history-printer-states.md \
  docs/uiux/uix-8c-06-receipt-history-printer-audit.md \
  docs/uiux/uix-8c-06-premium-receipt.md \
  docs/uiux/uix-8c-06-premium-transaction-history.md \
  docs/architecture/uix-8c-06-receipt-binding-and-history-reconciliation.md \
  docs/architecture/uix-8c-06-printer-failure-state-architecture.md \
  docs/testing/uix-8c-06-receipt-history-printer-test-matrix.md \
  docs/security/uix-8c-06-receipt-printer-threat-model.md \
  docs/deployment/uix-8c-06-deployment-evidence.md; do
  need_file "$d"
done

# 3. Pure presentation components exist.
for f in "$PROJECTION" "$PROJECTOR" "$RCPT_VM" "$RCPT_STATE" "$HIST_MODELS" \
         "$RECONCILER" "$HIST_VM" "$COORD" "$PSTATE" "$PCONN" "$BTCONN"; do
  need_file "$f"
done

# 4. Receipt binds to a stable transaction identity (UIX8C-R172).
need_grep "$PROJECTION" "class ReceiptIdentity" "ReceiptIdentity type exists (UIX8C-R172)"
need_grep "$PROJECTION" "clientReference" "receipt identity carries clientReference (UIX8C-R172)"
need_grep "$PROJECTION" "serverSaleId" "receipt identity carries serverSaleId (UIX8C-R172)"
need_grep "$RCPT_VM" "boundIdentity" "ViewModel guards the bound identity (UIX8C-R173)"
need_grep "$RCPT_VM" "identity.matches" "publish only when identity matches request (UIX8C-R173/R190)"

# 5. Truthful receipt states, durable save never synced (UIX8C-R175/R176).
for s in OFFLINE_PENDING SYNCING SYNCED FAILED CONFLICT ONLINE_SUCCESS; do
  need_grep "$PROJECTION" "$s" "receipt state $s present (UIX8C-R184)"
done
need_grep "$PROJECTOR" "OFFLINE_PENDING" "durable/unknown status projects to OFFLINE_PENDING, never SYNCED (UIX8C-R175)"

# 6. Whole-Rupiah money reuse + no unsafe Float/Double on the receipt money path.
need_grep "$PROJECTOR" "RupiahMoney" "projector reuses canonical RupiahMoney (UIX8C-R179)"
deny_grep "$PROJECTION" "Double" "no Double on receipt projection money path (UIX8C-R179)"
deny_grep "$PROJECTION" "Float" "no Float on receipt projection money path (UIX8C-R179)"
if grep -qE 'toFloat\(|toDouble\(' "$PROJECTOR" 2>/dev/null; then
  bad "projector must not use unsafe float conversion (UIX8C-R179)"
else pass "no unsafe float conversion in projector (UIX8C-R179)"; fi

# 7. Local/server history merge + one-row-per-transaction + conflict (UIX8C-R181/R182).
need_grep "$RECONCILER" "mergeKey" "reconciler dedups by transaction identity mergeKey (UIX8C-R182)"
need_grep "$RECONCILER" "CONFLICT" "reconciler surfaces CONFLICT on mismatch (UIX8C-R160/R184)"
need_grep "$HIST_MODELS" "RETRY_SCHEDULED" "history distinguishes RETRY_SCHEDULED from FAILED (UIX8C-R184)"
need_grep "$HIST_VM" "TransactionHistoryReconciler" "history VM routes through the reconciler (UIX8C-R181)"

# 8. Printer non-financial authority — no transaction reference in the printer package.
if grep -rqE 'SalesRepository|OfflineSaleRepository|OfflineSalesSync|OfflineSyncStatus|SaleService|InventoryMovement|createOfflineCashSale|checkoutCash' "$PRINTER_DIR" 2>/dev/null; then
  bad "printer package must not reference a sale/payment/offline/sync/inventory authority (UIX8C-R191/R192)"
else
  pass "printer package holds no financial-transaction reference (UIX8C-R191/R192)"
fi
if grep -qE 'checkoutCash|submitCash|createOfflineCashSale|processPayment|\.pay\(' "$COORD" 2>/dev/null; then
  bad "coordinator must not call checkout/payment on reprint (UIX8C-R193)"
else pass "coordinator calls no checkout/payment on reprint (UIX8C-R193)"; fi

# 9. Typed printer failure states + typed PrintResult (UIX8C-R197).
for s in PERMISSION_REQUIRED PERMISSION_DENIED UNSUPPORTED ADAPTER_DISABLED \
         DEVICE_NOT_CONFIGURED DEVICE_UNAVAILABLE CONNECTION_FAILED TIMEOUT \
         WRITE_FAILED INTERRUPTED UNKNOWN_SAFE_FAILURE; do
  need_grep "$PSTATE" "$s" "typed printer failure $s present (UIX8C-R197)"
done
need_grep "$PCONN" "PrinterFailure" "PrintResult.Failure is typed by PrinterFailure (UIX8C-R197)"

# 10. Bounded print (timeout + single active job), no unbounded loop (UIX8C-R198/R200).
need_grep "$BTCONN" "withTimeoutOrNull" "transport bounds connect+write with a timeout (UIX8C-R200)"
need_grep "$COORD" "AtomicBoolean" "coordinator admits at most one active print job (UIX8C-R198)"
if grep -rqE 'while *\(true\)' "$PRINTER_DIR" 2>/dev/null; then
  bad "printer package must not contain an unbounded loop (UIX8C-R200)"
else pass "no unbounded loop in printer package (UIX8C-R200)"; fi

# 11. Least-privilege Bluetooth permissions — CONNECT only, no SCAN, no location.
need_grep "$MANIFEST" "BLUETOOTH_CONNECT" "manifest declares BLUETOOTH_CONNECT (UIX8C-R196)"
deny_grep "$MANIFEST" "BLUETOOTH_SCAN" "no BLUETOOTH_SCAN permission (UIX8C-R195)"
deny_grep "$MANIFEST" "ACCESS_FINE_LOCATION" "no location permission for BT printing (UIX8C-R194)"
# Match actual API usage (call syntax / permission constant), not the FIX-BT-SCAN
# documentation comment that deliberately names the forbidden APIs.
if grep -rqE '\.(startDiscovery|cancelDiscovery)\(|permission\.BLUETOOTH_SCAN' "$PRINTER_DIR" 2>/dev/null; then
  bad "printer transport must not scan/discover (UIX8C-R195)"
else pass "printer transport performs no scan/discovery (UIX8C-R195)"; fi

# 12. Required tests present + content (stale-result / parity / merge / authority / a11y / font).
need_file "$TEST/ReceiptProjectorTest.kt"
need_file "$TEST/ReceiptViewModelTest.kt"
need_file "$TEST/TransactionHistoryReconcilerTest.kt"
need_file "$TEST/PrinterCoordinatorTest.kt"
need_file "$TEST/ReceiptHistoryStateDisplayTest.kt"
need_file "$TEST/ReceiptHistoryLayoutTest.kt"
need_file "$TEST/BluetoothPrinterConnectionTest.kt"
need_grep "$TEST/ReceiptViewModelTest.kt" "staleData_identityMismatch" "stale-result guard is tested (UIX8C-R173)"
need_grep "$TEST/ReceiptViewModelTest.kt" "restoresFromRoom" "process restoration from Room is tested (UIX8C-R187)"
need_grep "$TEST/ReceiptProjectorTest.kt" "parsesDecimalStringsToExactWholeRupiah" "whole-Rupiah parity tested (UIX8C-R177/R179)"
need_grep "$TEST/TransactionHistoryReconcilerTest.kt" "mergeToOneSyncedRow" "one-row-per-transaction merge tested (UIX8C-R181)"
need_grep "$TEST/TransactionHistoryReconcilerTest.kt" "isConflict" "conflict-not-silent-merge tested (UIX8C-R160)"
need_grep "$TEST/PrinterCoordinatorTest.kt" "AlreadyPrinting" "bounded single-job print tested (UIX8C-R198)"
need_grep "$TEST/PrinterCoordinatorTest.kt" "CreatesNoSecondLogicalPrintPath" "reprint-no-new-transaction tested (UIX8C-R193)"
need_grep "$TEST/ReceiptHistoryLayoutTest.kt" "130" "130% font resilience covered (UIX8C-R206)"
need_grep "$TEST/ReceiptHistoryLayoutTest.kt" "touch_target_min" "48dp touch targets covered (UIX8C-R202)"
need_grep "$TEST/ReceiptHistoryStateDisplayTest.kt" "haveDistinctLabels" "status-not-colour-alone covered (UIX8C-R205)"

# 13. Backend receipt/history parity regression fence.
need_file "$BTEST/Uix8c06ReceiptHistoryParityTest.php"
need_grep "$BTEST/Uix8c06ReceiptHistoryParityTest.php" "client_reference" \
  "backend fence exercises stable client_reference dedupe (UIX8C-R181)"
need_grep "$BTEST/Uix8c06ReceiptHistoryParityTest.php" "receipt" \
  "backend fence checks receipt/sale parity (UIX8C-R177/R178)"

# 14. Immutable failed physical run stays FAIL (UIX8C-R209/R003).
if [ -f "$FAILED_RUN" ]; then
  python3 - "$FAILED_RUN" <<'PY' || bad "failed physical run integrity check failed (UIX8C-R209/R003)"
import json, sys
d = json.load(open(sys.argv[1]))
rows = {r.get("id"): str(r.get("status","")).upper() for r in d.get("findings", [])}
problems = []
if rows.get("R11") != "FAIL": problems.append("R11 must stay FAIL")
if rows.get("R18") != "FAIL": problems.append("R18 must stay FAIL")
if rows.get("R01") != "PENDING": problems.append("R01 must stay PENDING")
if str(d.get("decision","")).upper() == "GO": problems.append("decision must not be GO")
if problems: print("; ".join(problems)); sys.exit(1)
PY
  [ "$fail" -eq 0 ] && pass "failed physical run R01/R11/R18 unchanged (UIX8C-R209/R003)" || true
else
  bad "missing immutable failed physical run record: $FAILED_RUN"
fi

# 15. UIX-7/UIX-8 runtime evidence stays deferred (decision != GO) (UIX8C-R210).
for pair in "UIX-7:$UIX7_EVID" "UIX-8:$UIX8_EVID"; do
  label="${pair%%:*}"; f="${pair#*:}"
  if [ -f "$f" ]; then
    dec="$(F="$f" python3 -c 'import json,os;print(str(json.load(open(os.environ["F"])).get("decision","")).upper())' 2>/dev/null)"
    case "$dec" in
      GO) bad "$label evidence decision is GO — must stay DEFERRED (UIX8C-R210)" ;;
      *) pass "$label still deferred (decision=$dec)" ;;
    esac
  else
    bad "missing $label runtime evidence: $f"
  fi
done

# 16. No premature UIX-7/UIX-8 GO tag (UIX8C-R210).
if git tag 2>/dev/null | grep -qE '^uix-7-.*-go$'; then
  bad "UIX-7 GO tag must not exist yet (UIX8C-R210)"
else pass "no premature UIX-7 GO tag (UIX8C-R210)"; fi
if git tag 2>/dev/null | grep -qE '^uix-8-android-cashier-premium.*-go$'; then
  bad "UIX-8 GO tag must not exist yet (UIX8C-R210)"
else pass "no premature UIX-8 GO tag (UIX8C-R210)"; fi
need_grep "$RULE" "UIX8C-R210" "sprint-scoped GO non-closure clause persisted (UIX8C-R210)"

# 17. No secret value in the touched source / docs (UIX8C-R199/R127).
SECRET_SCAN="$PROJECTION $PROJECTOR $RCPT_VM $HIST_VM $RECONCILER $COORD $PSTATE $BTCONN \
  docs/security/uix-8c-06-receipt-printer-threat-model.md \
  docs/deployment/uix-8c-06-deployment-evidence.md"
if grep -REn -- '-----BEGIN [A-Z ]*PRIVATE KEY-----|AKIA[0-9A-Z]{16}|xox[baprs]-[0-9A-Za-z-]{10,}|Bearer [A-Za-z0-9._-]{24,}|password[[:space:]]*[:=][[:space:]]*["'"'"'][^"'"'"']{6,}' $SECRET_SCAN 2>/dev/null; then
  bad "possible secret value present in touched source/docs (UIX8C-R199/R127)"
else
  pass "no secret value in touched source/docs (UIX8C-R199/R127)"
fi

echo
if [ "$fail" -eq 0 ]; then
  echo "UIX-8C-06 premium receipt / transaction-history / printer failure-state gate: PASS"
else
  echo "UIX-8C-06 premium receipt / transaction-history / printer failure-state gate: FAIL"
fi
exit "$fail"
