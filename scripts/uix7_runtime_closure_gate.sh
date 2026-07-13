#!/usr/bin/env bash
#
# UIX-7 physical-device runtime closure gate (UIX7-R052..UIX7-R070).
#
# Makes the physical-device runtime closure machine-verifiable and, above all,
# fail-closed: it can never let UIX-7 be declared GO while a runtime blocker
# remains or while any evidence is a placeholder / unsupported by a reference.
#
# Two modes:
#   * default (structural) — runs in CI on every candidate. Verifies the rules,
#     the evidence-doc shape, mandatory scenario coverage, the correct GO tag
#     name, absence of leaked secrets, and the safety invariant "no GO decision
#     while a PENDING/FAIL blocker remains".
#   * closure (UIX7_CLOSURE_GATE_MODE=closure) — the final pre-tag check. Also
#     requires zero PENDING/FAIL rows, no placeholders, every PASS backed by an
#     evidence reference, and that no physical-device row is satisfied by
#     emulator / unit-test evidence (UIX7-R062).
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }

RULE=.claude/rules/55-android-cashier-experience.md
FND=docs/foundation/uix-7-android-cashier-experience-remediation.md
PR=docs/PROJECT_RULES.md
DOC="${UIX7_CLOSURE_DOC:-docs/deployment/uix-7-physical-device-runtime-closure.md}"
CHECKLIST=docs/deployment/uix-7-android-runtime-verification-checklist.md
GO_TAG="uix-7-android-cashier-experience-remediation-go"
MODE="${UIX7_CLOSURE_GATE_MODE:-structural}"

echo "== UIX-7 physical-device runtime closure gate ($MODE) =="

# 1. Rules UIX7-R052..R070 persisted in rule 55 + foundation doc + PROJECT_RULES.
missing=""
for i in $(seq 52 70); do
  id="UIX7-R0$i"
  if grep -q "$id" "$RULE" && grep -q "$id" "$FND" && grep -q "$id" "$PR"; then :; else missing="$missing $id"; fi
done
[ -z "$missing" ] && pass "UIX7-R052..R070 persisted in rule 55 + foundation doc + PROJECT_RULES" \
                   || bad "runtime-closure rule ids not fully persisted:$missing"

# 2. Evidence doc exists with the mandatory sections (evidence schema).
[ -f "$DOC" ] || bad "missing runtime closure evidence doc $DOC"
for sect in "Device metadata" "Runtime verification matrix" "Synthetic data cleanup" \
            "VPS synchronization" "DaengtisiaMS non-regression" "Exact-match" \
            "GO / NO-GO decision"; do
  grep -qi "$sect" "$DOC" && pass "section present: $sect" || bad "evidence doc missing section: $sect"
done

# 3. Every mandatory runtime scenario is present in the matrix.
for scen in "Device activation" "Online transaction" "Financial total parity" \
            "Double-submit" "Offline durable save" "Process-kill restoration" \
            "Reconnect" "Sync acknowledgement" "No duplicate transaction" \
            "QRIS created" "QRIS confirmed" "QRIS duplicate callback" "QRIS failed/expired" \
            "Receipt current-transaction" "Receipt/history/backend parity" \
            "TalkBack" "focus order" "semantic labels" "touch targets" "font scaling" \
            "error announcements" "No crash / no ANR" "cleartext" "credential/token/PII"; do
  grep -qi -- "$scen" "$DOC" || bad "runtime matrix missing scenario: $scen"
done
[ "$fail" -eq 0 ] && pass "all mandatory runtime scenarios present in matrix" || true

# 4. Operator checklist still enumerates the on-device scenarios (UIX7-R062/R064).
[ -f "$CHECKLIST" ] || bad "missing operator checklist $CHECKLIST"

# 5. Correct GO tag name referenced; no wrong/typo tag.
grep -q "$GO_TAG" "$DOC" && pass "correct GO tag name referenced" || bad "GO tag name $GO_TAG not referenced in evidence doc"

# 6. No leaked secrets anywhere in the evidence doc (UIX7-R063) — always enforced.
if grep -Eniq '(bearer[[:space:]]+[a-z0-9._-]{8,}|-----BEGIN [A-Z ]*PRIVATE KEY-----|(password|passwd|pwd|secret|token|cookie|api[_-]?key)[[:space:]]*[:=][[:space:]]*[^[:space:]]+)' "$DOC"; then
  bad "possible secret/credential value present in evidence doc"
else
  pass "no secret/credential value pattern in evidence doc"
fi

# 7. SAFETY INVARIANT (always): no GO decision while a PENDING/FAIL blocker remains.
decision_go=0
grep -Eqi 'Decision:[^A-Za-z]*\*\*GO' "$DOC" && decision_go=1
pending_ct=$(grep -c '| PENDING ' "$DOC" || true)
failrow_ct=$(grep -c '| FAIL ' "$DOC" || true)
if [ "$decision_go" -eq 1 ] && { [ "$pending_ct" -ne 0 ] || [ "$failrow_ct" -ne 0 ]; }; then
  bad "evidence doc declares GO while $pending_ct PENDING / $failrow_ct FAIL blocker(s) remain"
else
  pass "no premature GO (GO=$decision_go, PENDING=$pending_ct, FAIL=$failrow_ct)"
fi

# 8. Every PASS row carries an evidence reference (no empty / dash / placeholder).
if grep -En '\| PASS \|[[:space:]]*(—|-|TBD|TODO|N/?A)?[[:space:]]*\|[[:space:]]*$' "$DOC" >/dev/null; then
  bad "a PASS row has no evidence reference"
else
  pass "every PASS row carries an evidence reference"
fi

# ---- closure-only assertions ----
if [ "$MODE" = "closure" ]; then
  # 9. No placeholders left.
  if grep -EnqiE '<PLACEHOLDER>|TBD|FILL_ME|xxxxxxx|\bTODO\b' "$DOC"; then
    bad "closure: evidence doc still has placeholders"
  else
    pass "closure: no placeholders in evidence doc"
  fi
  # 10. No PENDING and no FAIL row remains.
  [ "$pending_ct" -eq 0 ] && pass "closure: no PENDING rows" || bad "closure: $pending_ct PENDING row(s) remain"
  [ "$failrow_ct" -eq 0 ] && pass "closure: no FAIL rows" || bad "closure: $failrow_ct FAIL row(s) remain"
  # 11. Decision must be GO at closure.
  [ "$decision_go" -eq 1 ] && pass "closure: decision is GO" || bad "closure: decision is not GO"
  # 12. No physical-device row satisfied by emulator/unit-test evidence (UIX7-R062).
  if grep -Eni '\| PASS \|.*(emulator|10\.0\.2\.2|unit[ -]?test)' "$DOC" | grep -viE 'no .*emulator|no 10\.0\.2\.2' >/dev/null; then
    bad "closure: a PASS row is backed by emulator/unit-test evidence"
  else
    pass "closure: no physical-device row substituted by emulator/unit-test evidence"
  fi
fi

[ "$fail" -eq 0 ] || { echo "UIX-7 RUNTIME CLOSURE GATE: FAIL"; exit 1; }
echo "UIX-7 RUNTIME CLOSURE GATE: PASS"
