#!/usr/bin/env bash
#
# Regression tests for scripts/uix8_runtime_closure_gate.sh (UIX8-R047).
# Proves the gate is fail-closed: it cannot pass with a runtime blocker, with a
# GO decision while non-PASS rows remain, with emulator evidence on a
# hardware-required scenario, with a leaked secret, or in closure mode without
# real provenance.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"; cd "$ROOT"
GATE=scripts/uix8_runtime_closure_gate.sh
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
fails=0
ok(){ printf '  [ok]   %s\n' "$1"; }
no(){ printf '  [BAD]  %s\n' "$1"; fails=1; }

run(){ # run <mode> <manifest> [env...] -> sets RC
  local mode="$1" manifest="$2"; shift 2
  env "$@" UIX8_CLOSURE_GATE_MODE="$mode" UIX8_EVIDENCE_MANIFEST="$manifest" \
    bash "$GATE" >/dev/null 2>&1; RC=$?
}

echo "== uix8 closure gate regression =="

# 1. Real manifest, preflight -> PASS (honest GO_DEFERRED, structurally valid).
run preflight docs/deployment/uix-8-runtime-evidence.json
[ "$RC" -eq 0 ] && ok "preflight passes on the real (GO_DEFERRED) manifest" \
                || no "preflight should pass on the real manifest (rc=$RC)"

# 2. Real manifest, closure -> FAIL (decision is GO_DEFERRED, no provenance).
run closure docs/deployment/uix-8-runtime-evidence.json
[ "$RC" -ne 0 ] && ok "closure fails on GO_DEFERRED manifest" \
                || no "closure must fail while decision != GO"

# 3. decision GO but PENDING rows remain -> FAIL (safety invariant).
cat >"$TMP/go_with_pending.json" <<'JSON'
{"sprint":"UIX-8","go_tag":"x","candidate_commit":"deadbeef","decision":"GO",
 "uix7_closure_debt":"closed",
 "rows":[{"id":"a","classification":"hardware_independent","evidence_source":"emulator","status":"PASS"},
         {"id":"b","classification":"hardware_independent","evidence_source":"pending","status":"PENDING"}]}
JSON
run preflight "$TMP/go_with_pending.json"
[ "$RC" -ne 0 ] && ok "GO with a PENDING row is rejected" \
                || no "GO must be rejected while non-PASS rows remain"

# 4. emulator evidence on a hardware_dependent row -> FAIL.
cat >"$TMP/emu_on_hw.json" <<'JSON'
{"sprint":"UIX-8","go_tag":"x","candidate_commit":"deadbeef","decision":"GO_DEFERRED",
 "uix7_closure_debt":"open",
 "rows":[{"id":"printer","classification":"hardware_dependent","evidence_source":"emulator","status":"PASS"}]}
JSON
run preflight "$TMP/emu_on_hw.json"
[ "$RC" -ne 0 ] && ok "emulator evidence for a hardware-required scenario is rejected" \
                || no "emulator-on-hardware-required must be rejected"

# 5. leaked secret in manifest -> FAIL.
cat >"$TMP/secret.json" <<'JSON'
{"sprint":"UIX-8","go_tag":"x","candidate_commit":"","decision":"GO_DEFERRED",
 "uix7_closure_debt":"open","note":"authorization: Bearer abcdef123456",
 "rows":[{"id":"a","classification":"hardware_independent","evidence_source":"pending","status":"PENDING"}]}
JSON
run preflight "$TMP/secret.json"
[ "$RC" -ne 0 ] && ok "leaked secret pattern is rejected" \
                || no "leaked secret must be rejected"

# 6. All-PASS + GO + debt closed, closure WITH provenance asserts -> PASS.
cat >"$TMP/clean_go.json" <<JSON
{"sprint":"UIX-8","go_tag":"uix-8-nonexistent-tag","candidate_commit":"$(git rev-parse HEAD)",
 "decision":"GO","uix7_closure_debt":"closed",
 "rows":[{"id":"a","classification":"hardware_independent","evidence_source":"emulator","status":"PASS"}]}
JSON
run closure "$TMP/clean_go.json" UIX8_CI_GREEN=true UIX8_PR_MERGED=true UIX8_EXACT_MATCH=true UIX8_DMS_OK=true
[ "$RC" -eq 0 ] && ok "closure passes only with GO + all-PASS + debt closed + provenance" \
                || no "closure should pass on a fully-satisfied manifest (rc=$RC)"

# 7. Same clean manifest but MISSING one provenance assert -> FAIL.
run closure "$TMP/clean_go.json" UIX8_CI_GREEN=true UIX8_PR_MERGED=true UIX8_EXACT_MATCH=true
[ "$RC" -ne 0 ] && ok "closure fails when a provenance assert is missing" \
                || no "closure must fail without full provenance"

[ "$fails" -eq 0 ] && { echo "UIX-8 CLOSURE GATE TEST: PASS"; exit 0; } \
                   || { echo "UIX-8 CLOSURE GATE TEST: FAIL"; exit 1; }
