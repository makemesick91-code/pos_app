#!/usr/bin/env bash
# UIX-8C-04 offline CASH durability & idempotent-recovery gate (fail-closed).
#
# Enforces the permanent offline-durability / transport-safety / idempotency
# baseline (UIX8C-R096..R130) on the native cashier app (com.aishtech.poslite):
#   * rule ids UIX8C-R096..R130 persisted (modular rule + PROJECT_RULES);
#   * root-cause / architecture / test-matrix / threat-model / evidence docs;
#   * a typed transport classifier that is fail-closed (unknown -> Ineligible);
#   * NO broad catch-all offline fallback in the ViewModel; the fallback is
#     gated through the typed classifier only;
#   * QRIS offline is impossible on the offline path;
#   * atomic Room save path; cart is never cleared inside the repository (clear
#     is a UI decision made only after a durable save);
#   * a stable, reused clientReference path;
#   * bounded WorkManager retry (exponential backoff, network constraint, cap);
#   * orphan-SYNCING recovery;
#   * Android + backend idempotency regression tests present;
#   * no floating-point money introduced on the classifier/offline path;
#   * the immutable failed physical run stays FAIL; UIX-7/UIX-8 stay deferred;
#   * no premature UIX-7/UIX-8 GO tag; the sprint tag never asserts runtime GO;
#   * no secret value in the touched source/evidence.
# Fail-closed: any missing/ambiguous check fails the gate.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }

echo "== UIX-8C-04 offline cash durability gate =="

JAVA="android/app/src/main/java/com/aishtech/poslite"
TEST="android/app/src/test/java/com/aishtech/poslite"
BTEST="backend/tests/Feature"
RULE=".claude/rules/61-android-cashier-full-premium-delivery-foundation.md"
PROJECT_RULES="docs/PROJECT_RULES.md"
FAILED_RUN="docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json"
UIX7_EVID="docs/deployment/uix-7-runtime-evidence.json"
UIX8_EVID="docs/deployment/uix-8-runtime-evidence.json"

CLASSIFIER="$JAVA/core/network/TransportFailureClassifier.kt"
SALES="$JAVA/data/repository/SalesRepository.kt"
OFFLINE="$JAVA/data/repository/OfflineSaleRepository.kt"
VM="$JAVA/feature/cashier/CashierViewModel.kt"
DAO="$JAVA/data/local/dao/OfflineSaleDao.kt"
SCHED="$JAVA/feature/sync/OfflineSalesSyncScheduler.kt"

need_file(){ [ -f "$1" ] && pass "present: $1" || bad "missing: $1"; }
need_grep(){ grep -q "$2" "$1" 2>/dev/null && pass "$3" || bad "$3 (missing '$2' in $1)"; }
deny_grep(){ # file token label  -> FAIL if token present
  grep -q "$2" "$1" 2>/dev/null && bad "$3 (found '$2' in $1)" || pass "$3"; }

# 1. Rule ids UIX8C-R096..R130 persisted in BOTH the modular rule and PROJECT_RULES.
[ -f "$RULE" ] && pass "modular rule 61 present" || bad "missing $RULE"
[ -f "$PROJECT_RULES" ] && pass "PROJECT_RULES present" || bad "missing $PROJECT_RULES"
missing_ids=""
for i in $(seq 96 130); do
  id="$(printf 'UIX8C-R%03d' "$i")"
  if grep -q "$id" "$RULE" 2>/dev/null && grep -q "$id" "$PROJECT_RULES" 2>/dev/null; then :; else
    missing_ids="$missing_ids $id"
  fi
done
[ -z "$missing_ids" ] && pass "UIX8C-R096..R130 persisted (rule + PROJECT_RULES)" \
  || bad "UIX8C-R096..R130 not fully persisted:$missing_ids"

# 2. Required sprint docs.
for d in \
  docs/architecture/uix-8c-04-offline-cash-root-cause-analysis.md \
  docs/architecture/uix-8c-04-offline-cash-durability-architecture.md \
  docs/testing/uix-8c-04-offline-cash-idempotency-test-matrix.md \
  docs/security/uix-8c-04-offline-cash-threat-model.md \
  docs/deployment/uix-8c-04-deployment-evidence.md \
  docs/adr/0006-uix-8c-04-offline-cash-durability.md ; do
  need_file "$d"
done

# 3. Typed transport classifier present + fail-closed default (UIX8C-R098/R103).
need_file "$CLASSIFIER"
need_grep "$CLASSIFIER" "Eligible" "classifier has an Eligible outcome (UIX8C-R098)"
need_grep "$CLASSIFIER" "Ineligible" "classifier has an Ineligible outcome (UIX8C-R103)"
# The unknown/else path must be Ineligible (fail-closed), never Eligible.
if grep -qE 'else *->.*Eligible[^(]' "$CLASSIFIER" 2>/dev/null; then
  bad "classifier else/default must be Ineligible, never Eligible (UIX8C-R103)"
else
  pass "classifier fails closed (unknown -> Ineligible) (UIX8C-R103)"
fi
# TLS/security must be classified Ineligible.
need_grep "$CLASSIFIER" "SSL" "TLS/certificate failure classified (UIX8C-R103)"

# 4. NO broad catch-all offline fallback in the ViewModel (UIX8C-R099..R103).
# A legitimate product-load catch is allowed; what is forbidden is queueing an
# offline sale from inside a catch block (that would launder any error, incl. a
# canonical rejection, into offline "success").
if grep -A3 'catch' "$VM" 2>/dev/null | grep -qE 'createOfflineCashSale|saveOfflineFallback'; then
  bad "offline fallback must not be reached from a catch-all block (UIX8C-R103)"
else
  pass "no offline fallback from a catch-all block (UIX8C-R103)"
fi
# The fallback is reached ONLY via the typed TransportUnavailable outcome.
need_grep "$VM" "CheckoutOutcome.TransportUnavailable" \
  "offline fallback is gated on the typed transport outcome (UIX8C-R098)"
if grep -A3 'CheckoutOutcome.TransportUnavailable' "$VM" 2>/dev/null | grep -q 'saveOfflineFallback'; then
  pass "TransportUnavailable -> durable offline save (UIX8C-R098)"
else
  bad "TransportUnavailable must route to the durable offline save (UIX8C-R098)"
fi

# 5. Governed classification wired into the online submit (UIX8C-R098..R103).
need_grep "$SALES" "TransportFailureClassifier" \
  "online submit classifies transport failures (UIX8C-R098)"
need_grep "$SALES" "Rejected" "a canonical HTTP rejection is a distinct outcome, never offline (UIX8C-R099..R102)"

# 6. QRIS offline is impossible on the offline path (UIX8C-R096). Documentation
#    may state QRIS is forbidden; what must be absent is any QRIS request/API use.
if grep -qE 'Qris(Payment|Request)|createQrisPayment' "$OFFLINE" 2>/dev/null; then
  bad "offline path must be CASH-only; no QRIS request/API (UIX8C-R096)"
else
  pass "offline path is CASH-only, no QRIS request/API (UIX8C-R096)"
fi
need_grep "$OFFLINE" "CashPaymentRequestDto" "offline payment is CASH (UIX8C-R096)"
need_grep "$OFFLINE" "ANDROID_OFFLINE" "offline source is ANDROID_OFFLINE CASH (UIX8C-R096)"

# 7. Atomic Room save path (UIX8C-R106).
need_grep "$DAO" "@Transaction" "offline save is a single Room @Transaction (UIX8C-R106)"
need_grep "$DAO" "insertOfflineSaleWithItems" "atomic header+items insert present (UIX8C-R106)"

# 8. Cart-clear-after-durable-save: the repository NEVER clears the cart, and the
#    ViewModel clears only on a durable Saved outcome (UIX8C-R107/R108).
deny_grep "$OFFLINE" "cart.clear" "repository never clears the cart (clear is a UI decision after a durable save) (UIX8C-R107)"
need_grep "$VM" "saveOfflineFallback" "ViewModel owns the clear-after-durable-save fallback (UIX8C-R107)"
if grep -A6 'is OfflineSaleRepository.SaveResult.Saved' "$VM" 2>/dev/null | grep -q 'cart.clear()'; then
  pass "cart cleared only on a durable Saved outcome (UIX8C-R107)"
else
  bad "cart must clear only on SaveResult.Saved (UIX8C-R107)"
fi

# 9. Stable, reused clientReference (UIX8C-R097).
need_grep "$VM" "checkoutReference" "ViewModel mints one stable checkout reference (UIX8C-R097)"
need_grep "$VM" "pendingCheckoutReference" "stable reference is held across attempts (UIX8C-R097)"
need_grep "$OFFLINE" "clientReference: String? = null" \
  "offline save accepts+reuses the supplied stable reference (UIX8C-R097)"
need_grep "$OFFLINE" "findByClientReference" "offline save reconciles by reference (idempotent) (UIX8C-R109)"

# 10. Bounded WorkManager retry (UIX8C-R115).
need_grep "$SCHED" "BackoffPolicy.EXPONENTIAL" "sync uses exponential backoff (UIX8C-R115)"
need_grep "$SCHED" "NetworkType.CONNECTED" "sync is network-constrained (UIX8C-R115)"
need_grep "$OFFLINE" "MAX_SYNC_ATTEMPTS" "sync retry is bounded by a cap (UIX8C-R115)"

# 11. Orphan-SYNCING recovery (UIX8C-R117).
need_grep "$DAO" "'SYNCING'" "orphan SYNCING rows are re-selected for recovery (UIX8C-R117)"

# 12. Android regression tests present (UIX8C-R109/R112/R116).
for t in \
  "$TEST/TransportFailureClassifierTest.kt" \
  "$TEST/CashierCheckoutFallbackTest.kt" \
  "$TEST/OfflineSaleRepositoryTest.kt" ; do
  need_file "$t"
done

# 13. Backend idempotency regression test present (UIX8C-R118..R122).
need_file "$BTEST/OfflineCashDurabilityIdempotencyTest.php"
need_grep "$BTEST/OfflineCashDurabilityIdempotencyTest.php" "client_reference" \
  "backend test exercises stable client_reference replay (UIX8C-R118)"

# 14. No floating-point money introduced on the classifier/offline money path (UIX8C-R121).
if grep -qE '\.toFloat\(|\bFloat\b' "$CLASSIFIER" 2>/dev/null; then
  bad "no Float money/type allowed in the classifier (UIX8C-R121)"
else
  pass "no Float on the classifier path (UIX8C-R121)"
fi
need_grep "$OFFLINE" "RupiahMoney" "offline money uses whole-Rupiah RupiahMoney (UIX8C-R121)"

# 15. Immutable failed physical run stays FAIL (UIX8C-R129/R003).
if [ -f "$FAILED_RUN" ]; then
  python3 - "$FAILED_RUN" <<'PY' || bad "failed physical run integrity check failed (UIX8C-R129/R003)"
import json, sys
d = json.load(open(sys.argv[1]))
rows = {r.get("id"): r.get("status") for r in d.get("findings", [])}
def is_pass(v): return str(v).upper() == "PASS"
problems = []
if is_pass(rows.get("R11", "FAIL")): problems.append("R11 must stay FAIL")
if is_pass(rows.get("R18", "FAIL")): problems.append("R18 must stay FAIL")
if is_pass(rows.get("R01", "PENDING")): problems.append("R01 must stay PENDING")
if str(d.get("decision", "")).upper() == "GO": problems.append("decision must not be GO")
if problems: print("; ".join(problems)); sys.exit(1)
PY
  [ "$fail" -eq 0 ] && pass "failed physical run R01/R11/R18 unchanged (UIX8C-R129/R003)" || true
else
  bad "missing immutable failed physical run record: $FAILED_RUN"
fi

# 16. UIX-7/UIX-8 runtime evidence stays deferred (decision != GO) (UIX8C-R130).
for pair in "UIX-7:$UIX7_EVID" "UIX-8:$UIX8_EVID"; do
  label="${pair%%:*}"; f="${pair#*:}"
  if [ -f "$f" ]; then
    dec="$(F="$f" python3 -c 'import json,os;print(str(json.load(open(os.environ["F"])).get("decision","")).upper())' 2>/dev/null)"
    if [ "$dec" = "GO" ]; then bad "$label evidence decision is GO — must stay DEFERRED (UIX8C-R130)"; else pass "$label still deferred (decision=$dec)"; fi
  else
    bad "missing $label runtime evidence: $f"
  fi
done

# 17. No premature UIX-7/UIX-8 GO tag.
if git tag 2>/dev/null | grep -qE '^uix-7-.*-go$'; then
  bad "UIX-7 GO tag must not exist yet (UIX8C-R130)"
else pass "no premature UIX-7 GO tag (UIX8C-R130)"; fi
if git tag 2>/dev/null | grep -qE '^uix-8-android-cashier-premium.*-go$'; then
  bad "UIX-8 GO tag must not exist yet (UIX8C-R130)"
else pass "no premature UIX-8 GO tag (UIX8C-R130)"; fi

# 18. Sprint-tag semantics persisted (the R130 non-closure clause).
need_grep "$RULE" "UIX8C-R130" "sprint-scoped GO non-closure clause persisted (UIX8C-R130)"

# 19. No secret value in the touched source / evidence (UIX8C-R127).
SECRET_SCAN="$CLASSIFIER $SALES $OFFLINE $VM docs/security/uix-8c-04-offline-cash-threat-model.md docs/deployment/uix-8c-04-deployment-evidence.md"
if grep -REn -- '-----BEGIN [A-Z ]*PRIVATE KEY-----|AKIA[0-9A-Z]{16}|xox[baprs]-[0-9A-Za-z-]{10,}|Bearer [A-Za-z0-9._-]{24,}|password[[:space:]]*[:=][[:space:]]*["'"'"'][^"'"'"']{6,}' $SECRET_SCAN 2>/dev/null; then
  bad "possible secret value present in touched source/evidence (UIX8C-R127)"
else
  pass "no secret value in touched source/evidence (UIX8C-R127)"
fi

echo
if [ "$fail" -eq 0 ]; then
  echo "UIX-8C-04 offline cash durability gate: PASS"
else
  echo "UIX-8C-04 offline cash durability gate: FAIL"
fi
exit "$fail"
