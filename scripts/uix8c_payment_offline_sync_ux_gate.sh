#!/usr/bin/env bash
# UIX-8C-05 premium cash payment & offline-sync recovery UX gate (fail-closed).
#
# Enforces the permanent payment-presentation / sync-recovery-UX / manual-retry
# baseline (UIX8C-R131..R170) on the native cashier app (com.aishtech.poslite):
#   * rule ids UIX8C-R131..R170 persisted (modular rule 61 + PROJECT_RULES);
#   * required audit/architecture/state-machine/recovery/test-matrix/threat-model/
#     deployment/ADR docs;
#   * the pure presentation components exist (TenderValidator, QuickTenderCalculator,
#     PaymentUiState + PaymentUiStateMapper, SyncRecoveryPresenter);
#   * the payment sheet uses the canonical design system and the canonical
#     whole-Rupiah formatter/parser — NO Float/Double on the money path;
#   * the UIX-8C-04 transaction foundation is REUSED, not duplicated: exactly one
#     TransportFailureClassifier, one offline-persistence path, one WorkManager
#     pipeline, and one stable-clientReference lifecycle — the new UI code never
#     re-implements transport classification, Room persistence, or sync enqueue;
#   * the ViewModel double-submit guard, insufficient-tender blocking, and
#     SYNCED-only-on-ack invariants hold; QRIS offline stays impossible;
#   * the state-machine / process-restoration / manual-retry / font-scale /
#     accessibility tests + the backend idempotency regression are present;
#   * the immutable failed physical run stays FAIL; UIX-7/UIX-8 stay deferred;
#   * no premature UIX-7/UIX-8 GO tag; no secret value in the touched source/docs.
# Fail-closed: any missing/ambiguous check fails the gate.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }

echo "== UIX-8C-05 payment / offline-sync recovery UX gate =="

JAVA="android/app/src/main/java/com/aishtech/poslite"
TEST="android/app/src/test/java/com/aishtech/poslite"
BTEST="backend/tests/Feature"
RULE=".claude/rules/61-android-cashier-full-premium-delivery-foundation.md"
PROJECT_RULES="docs/PROJECT_RULES.md"
FAILED_RUN="docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json"
UIX7_EVID="docs/deployment/uix-7-runtime-evidence.json"
UIX8_EVID="docs/deployment/uix-8-runtime-evidence.json"

VALIDATOR="$JAVA/feature/cashier/TenderValidator.kt"
QUICK="$JAVA/feature/cashier/QuickTenderCalculator.kt"
UISTATE="$JAVA/feature/cashier/PaymentUiState.kt"
MAPPER="$JAVA/feature/cashier/PaymentUiStateMapper.kt"
RECOVERY="$JAVA/feature/sync/SyncRecoveryPresenter.kt"
SHEET="$JAVA/feature/cashier/PaymentSheetFragment.kt"
LAYOUT="android/app/src/main/res/layout/view_payment_sheet.xml"
VM="$JAVA/feature/cashier/CashierViewModel.kt"
MONEY="$JAVA/core/money/RupiahMoney.kt"
CLASSIFIER="$JAVA/core/network/TransportFailureClassifier.kt"
OFFLINE="$JAVA/data/repository/OfflineSaleRepository.kt"
SCHED="$JAVA/feature/sync/OfflineSalesSyncScheduler.kt"

need_file(){ [ -f "$1" ] && pass "present: $1" || bad "missing: $1"; }
need_grep(){ grep -q "$2" "$1" 2>/dev/null && pass "$3" || bad "$3 (missing '$2' in $1)"; }
deny_grep(){ grep -q "$2" "$1" 2>/dev/null && bad "$3 (found '$2' in $1)" || pass "$3"; }

# 1. Rule ids UIX8C-R131..R170 persisted in BOTH the modular rule and PROJECT_RULES.
[ -f "$RULE" ] && pass "modular rule 61 present" || bad "missing $RULE"
[ -f "$PROJECT_RULES" ] && pass "PROJECT_RULES present" || bad "missing $PROJECT_RULES"
missing_ids=""
for i in $(seq 131 170); do
  id="$(printf 'UIX8C-R%03d' "$i")"
  if grep -q "$id" "$RULE" 2>/dev/null && grep -q "$id" "$PROJECT_RULES" 2>/dev/null; then :; else
    missing_ids="$missing_ids $id"
  fi
done
[ -z "$missing_ids" ] && pass "UIX8C-R131..R170 persisted (rule + PROJECT_RULES)" \
  || bad "UIX8C-R131..R170 not fully persisted:$missing_ids"

# 2. Required sprint docs.
for d in \
  docs/uiux/uix-8c-05-payment-offline-sync-audit.md \
  docs/uiux/uix-8c-05-premium-cash-payment-sheet.md \
  docs/architecture/uix-8c-05-payment-sync-state-machine.md \
  docs/architecture/uix-8c-05-process-recovery-and-manual-retry.md \
  docs/testing/uix-8c-05-payment-offline-sync-test-matrix.md \
  docs/security/uix-8c-05-payment-sync-threat-model.md \
  docs/deployment/uix-8c-05-deployment-evidence.md \
  docs/adr/0007-uix-8c-05-payment-sync-state-machine.md ; do
  need_file "$d"
done

# 3. Pure presentation components present (UIX8C-R131/R135..R148/R157..R160).
for f in "$VALIDATOR" "$QUICK" "$UISTATE" "$MAPPER" "$RECOVERY"; do need_file "$f"; done

# 4. Payment sheet uses the canonical design system + no hardcoded hex (UIX8C-R131).
need_file "$SHEET"; need_file "$LAYOUT"
need_grep "$LAYOUT" "Widget.Aish.Button.Pay" "payment sheet uses canonical Widget.Aish CTA (UIX8C-R131)"
need_grep "$LAYOUT" "NestedScrollView" "payment sheet root scrolls (CTA reachable at 130% font) (UIX8C-R166)"
if grep -Eq '(android:[a-zA-Z]+|app:[a-zA-Z]+)="#[0-9a-fA-F]{3,8}"' "$LAYOUT" 2>/dev/null; then
  bad "payment sheet must not hardcode hex colours (UIX8C-R131)"
else
  pass "no hardcoded hex in the payment sheet (UIX8C-R131)"
fi

# 5. Whole-Rupiah money reuse + NO Float/Double on the new money path (UIX8C-R136).
need_file "$MONEY"
need_grep "$VALIDATOR" "RupiahMoney" "tender validator uses the canonical whole-Rupiah type (UIX8C-R136)"
need_grep "$SHEET" "TenderValidator" "payment sheet validates through the pure TenderValidator (UIX8C-R137/R139)"
need_grep "$SHEET" "QuickTenderCalculator" "payment sheet derives quick tenders from the canonical calculator (UIX8C-R138)"
for f in "$VALIDATOR" "$QUICK" "$MAPPER" "$RECOVERY"; do
  if grep -Eq '\bFloat\b|\bDouble\b|\.toFloat\(|\.toDouble\(' "$f" 2>/dev/null; then
    bad "no Float/Double financial type allowed in $f (UIX8C-R136)"
  else
    pass "no Float/Double money in $(basename "$f") (UIX8C-R136)"
  fi
done

# 6. UIX-8C-04 transport classifier REUSED, not duplicated (UIX8C-R132/R150).
need_file "$CLASSIFIER"
# Exactly one class/object named TransportFailureClassifier in the whole tree.
tfc_count="$(grep -rlE '(object|class) +TransportFailureClassifier' "$JAVA" 2>/dev/null | wc -l | tr -d ' ')"
[ "$tfc_count" = "1" ] && pass "exactly one TransportFailureClassifier (no second classifier) (UIX8C-R150)" \
  || bad "there must be exactly one TransportFailureClassifier (found $tfc_count) (UIX8C-R150)"
# The NEW UI components must not re-implement transport classification.
if grep -Eq 'SSLException|UnknownHostException|SocketTimeoutException|ConnectException' "$VALIDATOR" "$QUICK" "$MAPPER" "$RECOVERY" "$SHEET" 2>/dev/null; then
  bad "new UI components must not re-classify transport failures — reuse TransportFailureClassifier (UIX8C-R150)"
else
  pass "new UI components do not re-implement transport classification (UIX8C-R150)"
fi

# 7. UIX-8C-04 offline persistence REUSED, not duplicated (UIX8C-R133/R152).
need_grep "$VM" "offline.createOfflineCashSale" "ViewModel reuses the canonical durable offline save (UIX8C-R152)"
# Only the canonical repository composes the atomic Room insert — no second path.
opath_count="$(grep -rl 'insertOfflineSaleWithItems' "$JAVA" 2>/dev/null | grep -v '/dao/' | wc -l | tr -d ' ')"
[ "$opath_count" = "1" ] && pass "exactly one offline-persistence caller path (OfflineSaleRepository) (UIX8C-R133)" \
  || bad "offline persistence must have exactly one caller path (found $opath_count) (UIX8C-R133)"
for f in "$VALIDATOR" "$QUICK" "$MAPPER" "$RECOVERY" "$SHEET"; do
  deny_grep "$f" "insertOfflineSaleWithItems" "new UI component does not persist offline rows directly ($(basename "$f")) (UIX8C-R133)"
done

# 8. Stable clientReference REUSED, not regenerated in new code (UIX8C-R151).
need_grep "$VM" "checkoutReference" "ViewModel reuses the one stable checkout reference (UIX8C-R151)"
need_grep "$VM" "pendingCheckoutReference" "stable reference held across attempts (UIX8C-R151)"
for f in "$SHEET" "$MAPPER" "$RECOVERY"; do
  deny_grep "$f" "UUID.randomUUID" "new UI component does not mint its own reference ($(basename "$f")) (UIX8C-R151)"
done

# 9. No SECOND WorkManager sync pipeline (UIX8C-R133). Only the canonical scheduler
#    enqueues; the new UI components never enqueue their own work.
need_file "$SCHED"
need_grep "$SCHED" "enqueueUniqueWork" "canonical unique-work sync scheduler present (UIX8C-R133)"
for f in "$VALIDATOR" "$QUICK" "$MAPPER" "$RECOVERY" "$SHEET"; do
  deny_grep "$f" "WorkManager" "new UI component does not build a second sync pipeline ($(basename "$f")) (UIX8C-R133)"
done
# The governed manual retry delegates to the canonical sync path, not a new one.
need_grep "$VM" "requestManualRetry" "ViewModel exposes a governed manual retry (UIX8C-R157)"
if grep -A2 'fun requestManualRetry' "$VM" 2>/dev/null | grep -q 'syncNow'; then
  pass "manual retry delegates to the canonical sync path (UIX8C-R157/R158)"
else
  bad "manual retry must delegate to the canonical sync path (UIX8C-R157)"
fi

# 10. ViewModel double-submit guard (UIX8C-R141..R143).
if grep -q 'CheckoutState.Submitting) return' "$VM" 2>/dev/null; then
  pass "ViewModel double-submit re-entry guard present (UIX8C-R142)"
else
  bad "ViewModel must guard a re-entrant submit (UIX8C-R142)"
fi

# 11. Insufficient-tender blocking (UIX8C-R139/R140).
need_grep "$VALIDATOR" "Insufficient" "validator has a distinct Insufficient outcome (UIX8C-R139)"
need_grep "$VALIDATOR" "canSubmit" "only a valid tender may submit (UIX8C-R139)"
need_grep "$SHEET" "canSubmit" "sheet enables confirm only for a valid tender (UIX8C-R139)"

# 12. SYNCED only on a canonical ack; queued/pending never claims sync (UIX8C-R147/R148).
need_grep "$MAPPER" "OfflineSyncStatus.SYNCED -> PaymentUiState.Synced" \
  "Synced is reachable ONLY from the canonical SYNCED status (UIX8C-R148)"
# The offline-saved checkout projection must be OfflineQueued, never Synced.
if grep -A3 'CheckoutState.OfflineSaved ->' "$MAPPER" 2>/dev/null | grep -q 'PaymentUiState.OfflineQueued'; then
  pass "a durable offline save projects to OfflineQueued, never Synced (UIX8C-R147)"
else
  bad "a durable offline save must project to OfflineQueued (UIX8C-R147)"
fi

# 13. QRIS is never offered on the offline/cash payment path (UIX8C-R134). A
#     documentation note ("QRIS is not offered here") is fine; what must be absent
#     is any QRIS request/API use on this sheet.
if grep -Eq 'createQrisPayment|Qris(Payment|Request|Intent)' "$SHEET" 2>/dev/null; then
  bad "the cash payment sheet must not offer QRIS (offline is CASH-only) (UIX8C-R134)"
else
  pass "cash payment sheet is CASH-only, no QRIS request/API (UIX8C-R134)"
fi

# 14. Required Android tests present (UIX8C-R146/R154/R157/R166/R163).
for t in \
  "$TEST/TenderValidatorTest.kt" \
  "$TEST/QuickTenderCalculatorTest.kt" \
  "$TEST/PaymentUiStateMapperTest.kt" \
  "$TEST/SyncRecoveryPresenterTest.kt" \
  "$TEST/PaymentSyncRecoveryViewModelTest.kt" \
  "$TEST/PaymentSheetLayoutTest.kt" ; do
  need_file "$t"
done
# State-machine coverage (invalid transition fails closed).
need_grep "$TEST/PaymentUiStateMapperTest.kt" "invalidTransitionsFailClosed" \
  "state-machine test proves invalid transitions fail closed (UIX8C-R146)"
# Process-restoration coverage (durable row survives recreation).
need_grep "$TEST/CashierCheckoutFallbackTest.kt" "process recreation" \
  "process-restoration test present (UIX8C-R154)"
# Manual-retry coverage (reuses queue, no new checkout).
need_grep "$TEST/PaymentSyncRecoveryViewModelTest.kt" "manual retry" \
  "manual-retry test present (UIX8C-R157)"
# Font-scale / accessibility coverage over the sheet layout.
need_grep "$TEST/PaymentSheetLayoutTest.kt" "large_font" \
  "font-scale (130%) reachability test present (UIX8C-R166)"
need_grep "$TEST/PaymentSheetLayoutTest.kt" "live_region" \
  "accessibility live-region test present (UIX8C-R163)"

# 15. Backend idempotency regression fence present (UIX8C-R118..R123).
need_file "$BTEST/PaymentSyncUxIdempotencyRegressionTest.php"
need_grep "$BTEST/PaymentSyncUxIdempotencyRegressionTest.php" "client_reference" \
  "backend regression exercises the stable client_reference replay (UIX8C-R118)"

# 16. Immutable failed physical run stays FAIL (UIX8C-R169/R003).
if [ -f "$FAILED_RUN" ]; then
  python3 - "$FAILED_RUN" <<'PY' || bad "failed physical run integrity check failed (UIX8C-R169/R003)"
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
  [ "$fail" -eq 0 ] && pass "failed physical run R01/R11/R18 unchanged (UIX8C-R169/R003)" || true
else
  bad "missing immutable failed physical run record: $FAILED_RUN"
fi

# 17. UIX-7/UIX-8 runtime evidence stays deferred (decision != GO) (UIX8C-R170).
for pair in "UIX-7:$UIX7_EVID" "UIX-8:$UIX8_EVID"; do
  label="${pair%%:*}"; f="${pair#*:}"
  if [ -f "$f" ]; then
    dec="$(F="$f" python3 -c 'import json,os;print(str(json.load(open(os.environ["F"])).get("decision","")).upper())' 2>/dev/null)"
    case "$dec" in
      GO) bad "$label evidence decision is GO — must stay DEFERRED (UIX8C-R170)" ;;
      *) pass "$label still deferred (decision=$dec)" ;;
    esac
  else
    bad "missing $label runtime evidence: $f"
  fi
done

# 18. No premature UIX-7/UIX-8 GO tag (UIX8C-R170).
if git tag 2>/dev/null | grep -qE '^uix-7-.*-go$'; then
  bad "UIX-7 GO tag must not exist yet (UIX8C-R170)"
else pass "no premature UIX-7 GO tag (UIX8C-R170)"; fi
if git tag 2>/dev/null | grep -qE '^uix-8-android-cashier-premium.*-go$'; then
  bad "UIX-8 GO tag must not exist yet (UIX8C-R170)"
else pass "no premature UIX-8 GO tag (UIX8C-R170)"; fi
# Sprint-tag non-closure clause persisted.
need_grep "$RULE" "UIX8C-R170" "sprint-scoped GO non-closure clause persisted (UIX8C-R170)"

# 19. No secret value in the touched source / docs (UIX8C-R161/R127).
SECRET_SCAN="$VALIDATOR $QUICK $UISTATE $MAPPER $RECOVERY $SHEET $VM \
  docs/security/uix-8c-05-payment-sync-threat-model.md \
  docs/deployment/uix-8c-05-deployment-evidence.md"
if grep -REn -- '-----BEGIN [A-Z ]*PRIVATE KEY-----|AKIA[0-9A-Z]{16}|xox[baprs]-[0-9A-Za-z-]{10,}|Bearer [A-Za-z0-9._-]{24,}|password[[:space:]]*[:=][[:space:]]*["'"'"'][^"'"'"']{6,}' $SECRET_SCAN 2>/dev/null; then
  bad "possible secret value present in touched source/docs (UIX8C-R161/R127)"
else
  pass "no secret value in touched source/docs (UIX8C-R161/R127)"
fi

echo
if [ "$fail" -eq 0 ]; then
  echo "UIX-8C-05 payment / offline-sync recovery UX gate: PASS"
else
  echo "UIX-8C-05 payment / offline-sync recovery UX gate: FAIL"
fi
exit "$fail"
