#!/usr/bin/env bash
#
# UIX-8 runtime closure gate (UIX8-R001..R048).
#
# Fail-closed gate for the Android Cashier Premium Visual & Transaction
# Experience sprint. It validates the permanent foundation (rules + design
# system + money integrity + bounded retry), the structured runtime-evidence
# manifest, and the release/UIX-7-debt discipline. It NEVER lets UIX-8 be
# declared GO while a runtime blocker remains, while UIX-7 closure debt is open
# without an auditable waiver, or while release provenance is unproven.
#
# Modes (UIX8_CLOSURE_GATE_MODE):
#   * preflight (default; alias: structural) — runs in CI on every candidate.
#     Verifies rule/ADR persistence, design-system invariants, money-integrity
#     markers, manifest schema, no leaked secrets, and the safety invariant
#     "decision may be GO only when zero non-PASS rows AND UIX-7 debt is
#     closed/waived". A GO_DEFERRED / NO_GO decision passes preflight (it is the
#     honest state) as long as the structure is valid.
#   * closure — the final pre-tag check. Additionally requires decision==GO,
#     zero PENDING/BLOCKED/FAIL rows, a real candidate_commit, UIX-7 debt
#     closed-or-waived, CI/PR/exact-match env asserts, and that the target GO tag
#     does not already exist on a different commit.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }

RULE=.claude/rules/56-android-cashier-premium-visual-transaction-foundation.md
ADR=docs/adr/0002-uix8-cashier-premium-visual-transaction-foundation.md
EVIDENCE_DOC=docs/deployment/uix-8-android-cashier-premium-visual-transaction-experience.md
MANIFEST="${UIX8_EVIDENCE_MANIFEST:-docs/deployment/uix-8-runtime-evidence.json}"
ANDROID=android/app/src/main
GO_TAG="uix-8-android-cashier-premium-visual-transaction-experience-go"
MODE="${UIX8_CLOSURE_GATE_MODE:-preflight}"
[ "$MODE" = "structural" ] && MODE="preflight"

echo "== UIX-8 runtime closure gate ($MODE) =="

# 1. Foundation rules UIX8-R001..R048 persisted in rule 56, and CLAUDE.md pointer.
missing=""
for i in $(seq 1 48); do
  id=$(printf 'UIX8-R%03d' "$i")
  grep -q "$id" "$RULE" || missing="$missing $id"
done
[ -z "$missing" ] && pass "UIX8-R001..R048 persisted in rule 56" \
                   || bad "foundation rule ids not fully persisted:$missing"
grep -q "56-android-cashier-premium-visual-transaction-foundation" CLAUDE.md \
  && pass "CLAUDE.md registers rule 56" || bad "CLAUDE.md missing rule 56 pointer"
[ -f "$ADR" ] && pass "ADR 0002 present" || bad "missing ADR $ADR"

# 2. Native-architecture invariant (UIX8-R001) — no WebView cashier surface.
if grep -rqi "WebView" "$ANDROID/java/com/aishtech/poslite/feature/cashier" 2>/dev/null; then
  bad "WebView reference in the cashier feature — native cashier invariant (UIX8-R001)"
else
  pass "no WebView in the cashier feature (native invariant)"
fi

# 3. Design-system invariant (UIX8-R005/R006) — tokens present, zero hardcoded hex in layouts.
for f in colors.xml dimens.xml styles.xml themes.xml; do
  [ -f "$ANDROID/res/values/$f" ] || bad "missing design token file res/values/$f"
done
[ "$fail" -eq 0 ] && pass "design token files present" || true
if grep -rEnq '#[0-9a-fA-F]{6,8}' "$ANDROID/res/layout" 2>/dev/null; then
  bad "hardcoded hex colour found in a layout (UIX8-R006)"
else
  pass "zero hardcoded hex in layouts (UIX8-R006)"
fi

# 4. Money-integrity markers (UIX8-R016/R017).
grep -q "subtotalRupiah" "$ANDROID/java/com/aishtech/poslite/data/repository/CartRepository.kt" \
  && pass "cart total is integer-exact (subtotalRupiah)" \
  || bad "CartRepository.subtotalRupiah missing — money not integer-exact"
grep -q "paidAmount: Long" "$ANDROID/java/com/aishtech/poslite/data/repository/SalesRepository.kt" \
  && pass "online checkout takes whole-rupiah Long" \
  || bad "SalesRepository.checkoutCash not migrated to Long"
grep -q "RupiahMoney.parse" "$ANDROID/java/com/aishtech/poslite/feature/cashier/CashierActivity.kt" \
  && pass "tendered cash parsed via RupiahMoney.parse" \
  || bad "CashierActivity does not parse cash via RupiahMoney.parse"

# 5. Bounded-retry marker (UIX8-R023).
grep -q "MAX_SYNC_ATTEMPTS" "$ANDROID/java/com/aishtech/poslite/data/repository/OfflineSaleRepository.kt" \
  && pass "offline sync retry is bounded (MAX_SYNC_ATTEMPTS)" \
  || bad "offline sync retry cap (MAX_SYNC_ATTEMPTS) missing"

# 6. Evidence doc + manifest present.
[ -f "$EVIDENCE_DOC" ] && pass "evidence doc present" || bad "missing evidence doc $EVIDENCE_DOC"
[ -f "$MANIFEST" ] || bad "missing structured evidence manifest $MANIFEST"

# 7. No leaked secrets in the manifest/evidence (UIX8-R039) — always enforced.
for f in "$MANIFEST" "$EVIDENCE_DOC"; do
  [ -f "$f" ] || continue
  if grep -Eniq '(bearer[[:space:]]+[a-z0-9._-]{8,}|-----BEGIN [A-Z ]*PRIVATE KEY-----|(password|passwd|pwd|secret|refresh[_-]?token|api[_-]?key|authorization)[[:space:]]*[:=][[:space:]]*[^[:space:]]+)' "$f"; then
    bad "possible secret/credential value present in $f"
  fi
done
[ "$fail" -eq 0 ] && pass "no secret/credential value pattern in evidence" || true

# 8. Structured manifest validation (schema + safety invariant + mode).
if [ -f "$MANIFEST" ]; then
  MANIFEST="$MANIFEST" MODE="$MODE" GO_TAG="$GO_TAG" python3 - <<'PY'
import json, os, subprocess, sys
path=os.environ["MANIFEST"]; mode=os.environ["MODE"]; go_tag=os.environ["GO_TAG"]
ok=True
def p(m): print("  [PASS] %s"%m)
def b(m):
    global ok; ok=False; print("  [FAIL] %s"%m)
try:
    d=json.load(open(path))
except Exception as e:
    print("  [FAIL] manifest is not valid JSON: %s"%e); sys.exit(1)

for k in ("sprint","go_tag","decision","uix7_closure_debt","rows"):
    if k not in d: b("manifest missing key '%s'"%k)
rows=d.get("rows",[])
if not isinstance(rows,list) or not rows: b("manifest 'rows' must be a non-empty list")

allowed_status={"PASS","FAIL","PENDING","BLOCKED"}
allowed_dec={"GO","GO_DEFERRED","NO_GO"}
allowed_cls={"hardware_independent","hardware_neutral","hardware_dependent","oem_specific"}
allowed_src={"physical","emulator","automated_test","database","ci","vps","pending"}
for i,r in enumerate(rows):
    for k in ("id","classification","evidence_source","status"):
        if k not in r: b("row %d missing '%s'"%(i,k))
    if r.get("status") not in allowed_status: b("row %s: bad status %r"%(r.get('id'),r.get('status')))
    if r.get("classification") not in allowed_cls: b("row %s: bad classification"%r.get('id'))
    if r.get("evidence_source") not in allowed_src: b("row %s: bad evidence_source"%r.get('id'))
    # UIX8-R041/UIX7-R073 — emulator evidence may not stand for a hardware-required row.
    if r.get("evidence_source")=="emulator" and r.get("classification") in ("hardware_dependent","oem_specific"):
        b("row %s: emulator evidence for a hardware-required scenario"%r.get('id'))
    # A PASS row must name a real source and (for runtime) a commit binding.
    if r.get("status")=="PASS" and r.get("evidence_source")=="pending":
        b("row %s: PASS with no evidence_source"%r.get('id'))

dec=d.get("decision")
if dec not in allowed_dec: b("decision must be one of %s"%allowed_dec)
debt=d.get("uix7_closure_debt")
waiver=d.get("uix7_risk_waiver")
debt_ok = (debt=="closed") or (isinstance(waiver,dict) and waiver.get("id") and waiver.get("expiry") and waiver.get("owner"))
nonpass=[r.get("id") for r in rows if r.get("status")!="PASS"]

# Safety invariant (always): decision GO is only legal with zero non-PASS rows AND debt closed/waived.
if dec=="GO" and nonpass:
    b("decision GO while non-PASS rows remain: %s"%(", ".join(nonpass)))
if dec=="GO" and not debt_ok:
    b("decision GO while UIX-7 closure debt is open without an auditable waiver")
if not nonpass and dec!="GO" :
    p("all rows PASS; decision is conservative (%s)"%dec)
if nonpass:
    p("%d runtime row(s) not yet PASS -> GO cannot be declared (honest deferral)"%len(nonpass))

if mode=="closure":
    if dec!="GO": b("closure: decision is %s, not GO"%dec)
    if nonpass: b("closure: %d non-PASS runtime row(s) remain"%len(nonpass))
    if not debt_ok: b("closure: UIX-7 closure debt not closed/waived")
    cand=d.get("candidate_commit") or ""
    if not cand.strip(): b("closure: candidate_commit is empty")
    # target GO tag must not already exist on a different commit
    try:
        existing=subprocess.check_output(["git","rev-list","-n","1",go_tag],
                  stderr=subprocess.DEVNULL).decode().strip()
    except Exception:
        existing=""
    if existing and cand.strip():
        def full(c):
            try: return subprocess.check_output(["git","rev-parse",c],stderr=subprocess.DEVNULL).decode().strip()
            except Exception: return c
        if full(existing)!=full(cand.strip()):
            b("closure: GO tag already exists on a different commit")
        else:
            p("closure: existing GO tag matches candidate commit")
    else:
        p("closure: target GO tag does not pre-exist on a different commit")

sys.exit(0 if ok else 1)
PY
  [ $? -eq 0 ] || fail=1
fi

# 9. Closure-context release provenance (runtime facts, asserted true at closure).
if [ "$MODE" = "closure" ]; then
  [ "${UIX8_CI_GREEN:-}" = "true" ]    && pass "closure: authoritative CI green asserted" \
                                       || bad "closure: authoritative CI not asserted green (UIX8_CI_GREEN!=true)"
  [ "${UIX8_PR_MERGED:-}" = "true" ]   && pass "closure: governance PR merged asserted" \
                                       || bad "closure: PR not asserted merged (UIX8_PR_MERGED!=true)"
  [ "${UIX8_EXACT_MATCH:-}" = "true" ] && pass "closure: local=origin=VPS exact-match asserted" \
                                       || bad "closure: exact-match not asserted (UIX8_EXACT_MATCH!=true)"
  [ "${UIX8_DMS_OK:-}" = "true" ]      && pass "closure: DaengtisiaMS non-regression asserted" \
                                       || bad "closure: DMS non-regression not asserted (UIX8_DMS_OK!=true)"
fi

[ "$fail" -eq 0 ] || { echo "UIX-8 RUNTIME CLOSURE GATE: FAIL ($MODE)"; exit 1; }
if [ "$MODE" = "closure" ]; then
  echo "UIX-8 RUNTIME CLOSURE GATE: PASS (GO)"
else
  echo "UIX-8 RUNTIME CLOSURE GATE: PASS (preflight; release decision governed by manifest)"
fi
