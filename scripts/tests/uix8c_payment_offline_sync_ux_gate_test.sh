#!/usr/bin/env bash
#
# Regression tests for scripts/uix8c_payment_offline_sync_ux_gate.sh (UIX-8C-05).
# Proves the payment / offline-sync recovery UX gate is fail-closed: it passes on
# the real tree and rejects (1) a missing rule id, (2) Float/Double money on the
# new path, (3) a duplicated transport classifier, (4) a duplicated offline-save
# path, (5) a missing double-submit guard, (6) an offline save projected as Synced
# (queued-before-durability), (7) a new reference minted in new UI code, (8) SYNCED
# projected before a canonical ack, (9) QRIS on the payment sheet, (10) mutated
# historical failed-run evidence, (11) a premature UIX-7 GO tag, and (12) a secret
# value in touched evidence.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"; cd "$ROOT"
GATE=scripts/uix8c_payment_offline_sync_ux_gate.sh
fails=0
ok(){ printf '  [ok]   %s\n' "$1"; }
no(){ printf '  [BAD]  %s\n' "$1"; fails=1; }

echo "== uix8c payment / offline-sync recovery UX gate regression =="

JAVA="android/app/src/main/java/com/aishtech/poslite"
VALIDATOR="$JAVA/feature/cashier/TenderValidator.kt"
MAPPER="$JAVA/feature/cashier/PaymentUiStateMapper.kt"
SHEET="$JAVA/feature/cashier/PaymentSheetFragment.kt"
VM="$JAVA/feature/cashier/CashierViewModel.kt"
CLASSIFIER="$JAVA/core/network/TransportFailureClassifier.kt"
RULE=".claude/rules/61-android-cashier-full-premium-delivery-foundation.md"
FRUN="docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json"
BTEST="backend/tests/Feature/PaymentSyncUxIdempotencyRegressionTest.php"
THREAT="docs/security/uix-8c-05-payment-sync-threat-model.md"

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

# 2. Drop a UIX-8C-05 rule id -> FAIL.
SB="$(mksandbox)"; sed -i 's/UIX8C-R150/UIX8C-RXXX/g' "$SB/$RULE"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a dropped rule id is rejected (UIX8C-R131..R170)" || no "dropped rule id must be rejected"
rm -rf "$SB"

# 3. Float/Double money on the new path -> FAIL.
SB="$(mksandbox)"; printf '\nval leak: Double = 0.0\n' >> "$SB/$VALIDATOR"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "Float/Double money on the new path is rejected (UIX8C-R136)" || no "float money must be rejected"
rm -rf "$SB"

# 4. Duplicated transport classifier -> FAIL.
SB="$(mksandbox)"
printf 'package com.aishtech.poslite.core.network\nobject TransportFailureClassifier2 { }\nobject TransportFailureClassifier { }\n' \
  > "$SB/$JAVA/core/network/DupClassifier.kt"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a second TransportFailureClassifier is rejected (UIX8C-R150)" || no "duplicate classifier must be rejected"
rm -rf "$SB"

# 5. Duplicated offline-save path in new UI code -> FAIL.
SB="$(mksandbox)"; printf '\n    // test: insertOfflineSaleWithItems(x, y)\n' >> "$SB/$SHEET"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a duplicated offline-save path is rejected (UIX8C-R133)" || no "duplicate offline persistence must be rejected"
rm -rf "$SB"

# 6. Remove the double-submit guard -> FAIL.
SB="$(mksandbox)"; sed -i 's/CheckoutState.Submitting) return/CheckoutState.Submitting) noop()/g' "$SB/$VM"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a missing double-submit guard is rejected (UIX8C-R142)" || no "missing submit guard must be rejected"
rm -rf "$SB"

# 7. Project a durable offline save as Synced (queued-before-durability) -> FAIL.
SB="$(mksandbox)"
sed -i 's/CheckoutState.OfflineSaved -> PaymentUiState.OfflineQueued/CheckoutState.OfflineSaved -> PaymentUiState.Synced/' "$SB/$MAPPER"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "an offline save projected as Synced is rejected (UIX8C-R147)" || no "offline-before-durability must be rejected"
rm -rf "$SB"

# 8. Mint a new reference in new UI code -> FAIL.
SB="$(mksandbox)"; printf '\nval r = UUID.randomUUID()\n' >> "$SB/$MAPPER"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a new reference minted in new UI code is rejected (UIX8C-R151)" || no "new reference in UI must be rejected"
rm -rf "$SB"

# 9. SYNCED projected before a canonical ack -> FAIL.
SB="$(mksandbox)"
sed -i 's/OfflineSyncStatus.SYNCED -> PaymentUiState.Synced/OfflineSyncStatus.PENDING -> PaymentUiState.Synced/' "$SB/$MAPPER"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "SYNCED projected before a canonical ack is rejected (UIX8C-R148)" || no "SYNCED-before-ack must be rejected"
rm -rf "$SB"

# 10. QRIS on the payment sheet -> FAIL.
SB="$(mksandbox)"; printf '\n    // test: createQrisPayment(saleId)\n' >> "$SB/$SHEET"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "QRIS on the payment sheet is rejected (UIX8C-R134)" || no "QRIS offline must be rejected"
rm -rf "$SB"

# 11. Flip historical failed R11 FAIL -> PASS -> FAIL.
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
[ "$RC" -ne 0 ] && ok "flipping historical R11 to PASS is rejected (UIX8C-R169/R003)" || no "flipped R11 must be rejected"
rm -rf "$SB"

# 12. Premature UIX-7 GO tag -> FAIL.
SB="$(mksandbox)"
( cd "$SB" && git init -q && git -c user.email=t@t.co -c user.name=t commit --allow-empty -qm x \
  && git tag uix-7-android-cashier-experience-remediation-go ) >/dev/null 2>&1
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a premature UIX-7 GO tag is rejected (UIX8C-R170)" || no "premature UIX-7 GO tag must be rejected"
rm -rf "$SB"

# 13. Secret value in touched evidence -> FAIL.
SB="$(mksandbox)"; printf '\nAKIAABCDEFGHIJKLMNOP\n' >> "$SB/$THREAT"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a secret value in touched evidence is rejected (UIX8C-R161/R127)" || no "secret value must be rejected"
rm -rf "$SB"

[ "$fails" -eq 0 ] && { echo "UIX-8C-05 PAYMENT / OFFLINE-SYNC UX GATE TEST: PASS"; exit 0; } \
                   || { echo "UIX-8C-05 PAYMENT / OFFLINE-SYNC UX GATE TEST: FAIL"; exit 1; }
