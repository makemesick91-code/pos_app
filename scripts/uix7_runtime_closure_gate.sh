#!/usr/bin/env bash
#
# UIX-7 runtime closure gate (UIX7-R052..R080).
#
# Validates the STRUCTURED runtime-evidence manifest
# (docs/deployment/uix-7-runtime-evidence.json) against the runtime-evidence
# source governance (docs/governance/android-runtime-evidence-governance.md,
# policy v1.0.0). Classification-aware and fail-closed: it can never let UIX-7 be
# declared GO while a runtime blocker remains, while any authoritative row lacks
# its commit/APK binding, or while emulator evidence is used for a
# hardware-required scenario.
#
# It does NOT search for a bare "PASS" string (UIX7-R078): every row is parsed as
# structured data and judged against its own hardware classification and source.
#
# Modes (UIX7_CLOSURE_GATE_MODE):
#   * preflight (default; alias: structural) — runs in CI on every candidate.
#     Verifies rule persistence, manifest schema, classification/source legality,
#     binding completeness for PASS rows, no leaked secrets, and the safety
#     invariant "no GO decision while a PENDING/BLOCKED/FAIL row remains".
#   * closure — the final pre-tag check. Additionally requires a real
#     candidate_commit, zero PENDING/BLOCKED/FAIL rows, decision GO, and that the
#     target GO tag does not already exist on a different commit.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }

RULE=.claude/rules/55-android-cashier-experience.md
FND=docs/foundation/uix-7-android-cashier-experience-remediation.md
PR=docs/PROJECT_RULES.md
POLICY=docs/governance/android-runtime-evidence-governance.md
MANIFEST="${UIX7_EVIDENCE_MANIFEST:-docs/deployment/uix-7-runtime-evidence.json}"
GO_TAG="uix-7-android-cashier-experience-remediation-go"
MODE="${UIX7_CLOSURE_GATE_MODE:-preflight}"
[ "$MODE" = "structural" ] && MODE="preflight"

echo "== UIX-7 runtime closure gate ($MODE) =="

# 1. Rules UIX7-R052..R080 persisted in rule 55 + foundation doc + PROJECT_RULES.
missing=""
for i in $(seq 52 80); do
  id="UIX7-R0$i"
  if grep -q "$id" "$RULE" && grep -q "$id" "$FND" && grep -q "$id" "$PR"; then :; else missing="$missing $id"; fi
done
[ -z "$missing" ] && pass "UIX7-R052..R080 persisted in rule 55 + foundation doc + PROJECT_RULES" \
                   || bad "runtime-evidence rule ids not fully persisted:$missing"

# 2. Governance policy present.
[ -f "$POLICY" ] && grep -qi 'policy version' "$POLICY" && pass "runtime-evidence governance policy present" \
  || bad "missing/invalid governance policy $POLICY"

# 3. Manifest exists.
[ -f "$MANIFEST" ] || { bad "missing structured evidence manifest $MANIFEST"; }

# 4. No leaked secrets in the manifest (UIX7-R063) — always enforced.
if [ -f "$MANIFEST" ] && grep -Eniq '(bearer[[:space:]]+[a-z0-9._-]{8,}|-----BEGIN [A-Z ]*PRIVATE KEY-----|(password|passwd|pwd|secret|refresh[_-]?token|api[_-]?key|authorization)[[:space:]]*[:=][[:space:]]*[^[:space:]]+)' "$MANIFEST"; then
  bad "possible secret/credential value present in manifest"
else
  pass "no secret/credential value pattern in manifest"
fi

# 5. Structured manifest validation (schema + classification + binding + mode).
if [ -f "$MANIFEST" ]; then
  MANIFEST="$MANIFEST" MODE="$MODE" GO_TAG="$GO_TAG" \
  UIX7_GO_TAG_COMMIT_OVERRIDE="${UIX7_GO_TAG_COMMIT_OVERRIDE:-}" \
  python3 - <<'PY'
import json, os, re, subprocess, sys

path = os.environ["MANIFEST"]; mode = os.environ["MODE"]
go_tag = os.environ["GO_TAG"]
ok = True
def p(m): print("  [PASS] " + m)
def b(m):
    global ok; ok = False; print("  [FAIL] " + m)

try:
    with open(path) as f: data = json.load(f)
except Exception as e:
    print("  [FAIL] manifest is not valid JSON: %s" % e); sys.exit(1)

CLASS = {"hardware_independent","hardware_neutral","hardware_dependent","oem_specific"}
SOURCE = {"physical","emulator","automated_test","database","ci","vps","pending"}
RESULT = {"PASS","N/A","PENDING","BLOCKED","FAIL"}
PLACEHOLDER = re.compile(r'(?i)\b(TBD|TODO|FILL_ME|PENDING_FINAL|<[^>]*>|xxxxx)\b')
HEX64 = re.compile(r'^[0-9a-fA-F]{64}$')

for k in ("policy_version","go_tag","decision","scenarios"):
    if k not in data: b("manifest missing top-level field: %s" % k)
if data.get("go_tag") != go_tag:
    b("manifest go_tag %r != expected %r" % (data.get("go_tag"), go_tag))
scen = data.get("scenarios") or []
if not isinstance(scen, list) or not scen:
    b("manifest.scenarios must be a non-empty array")

cand = data.get("candidate_commit")
unchanged = data.get("app_source_unchanged_since")
if cand is not None and PLACEHOLDER.search(str(cand)):
    b("candidate_commit is a placeholder: %r" % cand)

seen = set()
counts = {r:0 for r in RESULT}
for s in scen:
    sid = s.get("scenario_id","<no-id>")
    for k in ("scenario_id","scenario_name","classification","evidence_source","result",
              "commit_sha","app_version","apk_sha256","build_variant","environment",
              "executed_at","verification_method","evidence_reference"):
        if k not in s: b("%s: missing field %s" % (sid, k))
    cl = s.get("classification"); src = s.get("evidence_source"); res = s.get("result")
    if cl not in CLASS: b("%s: bad classification %r" % (sid, cl))
    if src not in SOURCE: b("%s: bad evidence_source %r" % (sid, src))
    if res not in RESULT: b("%s: bad result %r" % (sid, res))
    if sid in seen: b("%s: duplicate scenario_id" % sid)
    seen.add(sid)
    counts[res] = counts.get(res,0) + 1

    # Hardware-required scenarios must NOT use emulator evidence (UIX7-R073).
    if cl in ("hardware_dependent","oem_specific") and src == "emulator":
        b("%s: emulator evidence is not admissible for %s (physical required)" % (sid, cl))

    if res == "PASS":
        if src == "pending": b("%s: PASS row has evidence_source 'pending'" % sid)
        for req in ("commit_sha","verification_method","evidence_reference"):
            if not str(s.get(req,"")).strip(): b("%s: PASS row missing %s" % (sid, req))
        if src in ("physical","emulator"):
            for req in ("app_version","apk_sha256","build_variant","environment","executed_at"):
                if not str(s.get(req,"")).strip(): b("%s: build-produced PASS missing %s" % (sid, req))
            ah = str(s.get("apk_sha256","")).strip()
            if ah and not HEX64.match(ah): b("%s: apk_sha256 is not 64 hex chars" % sid)
        # Staleness guard: when candidate_commit set, a PASS commit must match it
        # or the app_source_unchanged_since anchor (UIX7-R076).
        if cand:
            cs = str(s.get("commit_sha","")).strip()
            allowed = {str(cand)}
            if unchanged: allowed.add(str(unchanged))
            if cs and cs not in allowed:
                b("%s: stale evidence commit %r not in %r" % (sid, cs, sorted(allowed)))
    elif res == "N/A":
        if not str(s.get("evidence_reference","")).strip():
            b("%s: N/A row must carry a domain reason in evidence_reference" % sid)

if ok: p("manifest schema, classification, source, and binding rules satisfied")

# Safety invariant (always): no GO decision while a blocker remains.
decision = str(data.get("decision","")).strip()
go = decision.upper().startswith("GO")
blockers = counts.get("PENDING",0) + counts.get("BLOCKED",0) + counts.get("FAIL",0)
if go and blockers:
    b("decision is GO while %d PENDING/BLOCKED/FAIL row(s) remain" % blockers)
else:
    p("no premature GO (GO=%s, blockers=%d)" % (go, blockers))

if mode == "closure":
    if not cand or PLACEHOLDER.search(str(cand)):
        b("closure: candidate_commit must be a real commit SHA")
    else:
        p("closure: candidate_commit set")
    if blockers == 0: p("closure: no PENDING/BLOCKED/FAIL rows")
    else: b("closure: %d PENDING/BLOCKED/FAIL row(s) remain" % blockers)
    if go: p("closure: decision is GO")
    else: b("closure: decision is not GO")
    # Target GO tag must not already exist on a different commit.
    existing = os.environ.get("UIX7_GO_TAG_COMMIT_OVERRIDE","").strip()
    if not existing:
        try:
            existing = subprocess.check_output(
                ["git","rev-list","-n","1",go_tag], stderr=subprocess.DEVNULL
            ).decode().strip()
        except Exception:
            existing = ""
    if existing and cand:
        def full(c):
            try: return subprocess.check_output(["git","rev-parse",c],
                     stderr=subprocess.DEVNULL).decode().strip()
            except Exception: return c
        if full(existing) != full(str(cand)):
            b("closure: GO tag already exists on a different commit (%s != %s)" % (existing[:12], str(cand)[:12]))
        else:
            p("closure: existing GO tag matches candidate commit")
    else:
        p("closure: target GO tag does not pre-exist on a different commit")

sys.exit(0 if ok else 1)
PY
  [ $? -eq 0 ] || fail=1
fi

# 6. Closure-context release facts (release/exact-match provenance). These are
#    runtime facts, not manifest content, and must be asserted true at closure.
if [ "$MODE" = "closure" ]; then
  [ "${UIX7_CI_GREEN:-}" = "true" ]   && pass "closure: authoritative CI green asserted" \
                                      || bad "closure: authoritative CI not asserted green (UIX7_CI_GREEN!=true)"
  [ "${UIX7_PR_MERGED:-}" = "true" ]  && pass "closure: governance PR merged asserted" \
                                      || bad "closure: PR not asserted merged (UIX7_PR_MERGED!=true)"
  [ "${UIX7_EXACT_MATCH:-}" = "true" ] && pass "closure: local=origin=VPS exact-match asserted" \
                                       || bad "closure: exact-match not asserted (UIX7_EXACT_MATCH!=true)"
fi

[ "$fail" -eq 0 ] || { echo "UIX-7 RUNTIME CLOSURE GATE: FAIL"; exit 1; }
echo "UIX-7 RUNTIME CLOSURE GATE: PASS"
