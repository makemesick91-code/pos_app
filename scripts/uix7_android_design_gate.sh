#!/usr/bin/env bash
#
# UIX-7 Android Cashier Experience design/UX gate (UIX7-R001..R044).
#
# Static, build-free checks over the Android app (this environment has no Android
# SDK, so runtime/instrumented behavior is verified by CI and by operator device
# verification). Verifies: existing design tokens reused (no hardcoded off-system
# colors), truthful status is textual (never colour-only), canonical whole-rupiah
# money, offline durability recovery, ViewModel double-submit guard, QRIS
# online-only truthfulness, transport/backup security, least-privilege exported
# components, and that no Platform-Admin/Owner web surface leaks into the cashier
# app. Chains the UIX-6 (and thus UIX-5/4/3/2/1) gate — no success-by-skipping.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }

APP=android/app/src/main
MANIFEST="$APP/AndroidManifest.xml"
CASHIER_VM="$APP/java/com/aishtech/poslite/feature/cashier/CashierViewModel.kt"
CASHIER_ACT="$APP/java/com/aishtech/poslite/feature/cashier/CashierActivity.kt"
MONEY="$APP/java/com/aishtech/poslite/core/money/RupiahMoney.kt"
DAO="$APP/java/com/aishtech/poslite/data/local/dao/OfflineSaleDao.kt"

echo "== UIX-7 Android cashier experience gate =="

# 1. Rules documented (UIX7-R001..R044) in the modular rule + foundation doc.
[ -f .claude/rules/55-android-cashier-experience.md ] && pass "modular rule 55 present" || bad "missing modular rule 55"
[ -f docs/foundation/uix-7-android-cashier-experience-remediation.md ] && pass "UIX-7 foundation doc present" || bad "missing UIX-7 foundation doc"

# 2. Canonical whole-rupiah money type + no unsafe float money in cashier UI (UIX7-R018/R019).
[ -f "$MONEY" ] && pass "RupiahMoney canonical money type present" || bad "missing RupiahMoney"
grep -q 'RupiahMoney' "$CASHIER_ACT" && pass "cashier formats money via canonical formatter" || bad "cashier not using RupiahMoney"
if grep -nE 'NumberFormat\.getNumberInstance' "$CASHIER_ACT" >/dev/null 2>&1; then
  bad "cashier still has an inline NumberFormat money block (use RupiahMoney)"
else
  pass "no inline NumberFormat money block in cashier activity"
fi

# 3. Design tokens reused — no hardcoded hex colour in cashier layouts (UIX7-R029).
if grep -nE 'android:(background|textColor|tint|strokeColor)="#' "$APP"/res/layout/*.xml >/dev/null 2>&1; then
  bad "a layout hardcodes a hex colour instead of a @color token"
else
  pass "layouts use @color design tokens (no hardcoded hex)"
fi

# 4. Truthful status is textual, not colour-only (UIX7-R024/R030).
for s in cashier_sync_summary uix_sync_pending uix_sync_in_progress uix_sync_failed_action; do
  grep -q "\"$s\"" "$APP/res/values/strings.xml" && pass "status label '$s' present" || bad "missing status label '$s'"
done

# 5. Offline durability: interrupted in-flight (SYNCING) rows are recovered
#    (UIX7-R009/R012), and FAILED retries are bounded (UIX8-R023) so a poison row
#    cannot starve the queue. The eligible set is PENDING + orphaned SYNCING +
#    FAILED-under-cap; PENDING and SYNCING are never capped.
if grep -q "IN ('PENDING', 'SYNCING')" "$DAO"; then
  pass "offline retry queue recovers orphaned SYNCING rows"
else
  bad "offline retry queue does not recover orphaned SYNCING rows"
fi
if grep -q "syncStatus = 'FAILED' AND syncAttemptCount < :maxAttempts" "$DAO"; then
  pass "offline FAILED retry is bounded (UIX8-R023 anti-starvation)"
else
  bad "offline FAILED retry is not bounded (poison row can starve the queue)"
fi

# 6. Checkout double-submit guard is at the ViewModel, not UI-only (UIX7-R015/R025).
if grep -q 'is CheckoutState.Submitting) return' "$CASHIER_VM"; then
  pass "ViewModel double-submit guard present"
else
  bad "no ViewModel-level double-submit guard on checkout"
fi

# 7. QRIS truthfulness: online-only guard + distinct canonical status labels (UIX7-R020/R021/R022).
[ -f "$APP/java/com/aishtech/poslite/feature/qris/QrisOnlineOnlyGuard.kt" ] && pass "QRIS online-only guard present" || bad "missing QRIS online-only guard"
for s in uix_qris_waiting uix_qris_verifying uix_qris_paid uix_qris_expired uix_qris_failed uix_qris_settlement_pending; do
  grep -q "\"$s\"" "$APP/res/values/strings.xml" && pass "QRIS label '$s' present" || bad "missing QRIS label '$s'"
done

# 8. Transport & data security (UIX7-R006/R026/R027).
[ -f "$APP/res/xml/network_security_config.xml" ] && pass "network security config present" || bad "missing network_security_config.xml"
if grep -q 'android:usesCleartextTraffic="true"' "$MANIFEST"; then
  bad "manifest allows cleartext traffic app-wide"
else
  pass "no app-wide cleartext traffic"
fi
grep -q 'android:allowBackup="false"' "$MANIFEST" && pass "app backup disabled" || bad "allowBackup is not false"

# 9. Least-privilege exported components: only the launcher activity is exported (UIX7-R028).
exported_true=$(grep -c 'android:exported="true"' "$MANIFEST" || true)
if [ "$exported_true" -eq 1 ]; then
  pass "exactly one exported component (launcher)"
else
  bad "unexpected exported component count: $exported_true (expected 1 launcher)"
fi

# 10. No Platform-Admin/Owner web surface leaks into the cashier app (UIX7-R001).
if grep -REn '"/(admin|owner)/' "$APP/java" >/dev/null 2>&1; then
  bad "cashier app references an /admin or /owner web path"
else
  pass "no admin/owner web path in cashier app"
fi

# 11. Chain the prior design gates (UIX-6 -> UIX-5 -> ... -> UIX-1). No success-by-skipping.
bash scripts/uix6_design_gate.sh

[ "$fail" -eq 0 ] || { echo "UIX-7 ANDROID DESIGN GATE: FAIL"; exit 1; }
echo "UIX-7 ANDROID DESIGN GATE: PASS"
