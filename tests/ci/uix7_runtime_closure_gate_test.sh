#!/usr/bin/env bash
#
# Self-contained fail-closed regression tests for
# scripts/uix7_runtime_closure_gate.sh (UIX7-R052..R080, policy v1.0.0).
# No bats dependency. Proves the structured, classification-aware gate:
#   * PASSes preflight against the real manifest and FAILs closure while blockers
#     remain;
#   * accepts physical PASS and emulator PASS for hardware-independent rows;
#   * rejects emulator PASS for hardware-required rows (Bluetooth printer, NFC);
#   * rejects missing checksum / source, stale/wrong commit, non-structured data,
#     empty scenario set, invalid N/A;
#   * blocks closure on an unresolved blocker, exact-match failure, unmerged PR,
#     and a target-tag/commit mismatch;
#   * PASSes closure only on a genuinely complete, GO manifest with the release
#     facts asserted.
#
# Usage: bash tests/ci/uix7_runtime_closure_gate_test.sh
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"; cd "$ROOT"
GATE="$ROOT/scripts/uix7_runtime_closure_gate.sh"
REAL="docs/deployment/uix-7-runtime-evidence.json"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
PASS=0; FAIL=0

# check <name> <expect: pass|fail> <mode> <manifest> [extra env KEY=VAL ...]
check() {
  local name="$1" expect="$2" mode="$3" manifest="$4"; shift 4
  local rc got
  env "$@" UIX7_CLOSURE_GATE_MODE="$mode" UIX7_EVIDENCE_MANIFEST="$manifest" \
    bash "$GATE" >/dev/null 2>&1; rc=$?
  got="pass"; [ "$rc" -ne 0 ] && got="fail"
  if [ "$got" = "$expect" ]; then PASS=$((PASS+1)); printf 'ok   - %s (%s)\n' "$name" "$got"
  else FAIL=$((FAIL+1)); printf 'FAIL - %s : expected %s got %s (rc=%d)\n' "$name" "$expect" "$got" "$rc"; fi
}

# fixture <case> -> writes a manifest to $TMP/<case>.json and echoes the path.
fixture() {
  local case="$1" out="$TMP/$1.json"
  CASE="$case" OUT="$out" python3 - <<'PY'
import json, os
case = os.environ["CASE"]; out = os.environ["OUT"]
SHA = "abc1234"; APK = "43b5d599a7e5a1865d8adf1a594218d7feca914f37f76e13f3e19d76dac6e795"
def row(**kw):
    base = dict(scenario_id="X", scenario_name="n", classification="hardware_neutral",
        evidence_source="physical", result="PASS", commit_sha=SHA, app_version="0.1.0",
        apk_sha256=APK, build_variant="pilot", environment="AVD/device", executed_at="2026-07-14",
        verification_method="adb + scoped DB", evidence_reference="ref")
    base.update(kw); return base

# A genuinely-complete GO manifest: physical PASS + emulator PASS (hw-independent) + N/A.
scen = [
    row(scenario_id="R01", classification="hardware_neutral", evidence_source="physical"),
    row(scenario_id="R11", scenario_name="offline save", classification="hardware_independent",
        evidence_source="emulator"),
    row(scenario_id="H02", scenario_name="bluetooth printer", classification="hardware_dependent",
        evidence_source="physical", result="N/A", commit_sha="", app_version="", apk_sha256="",
        build_variant="", environment="", executed_at="", verification_method="",
        evidence_reference="not exposed in cash-only cashier"),
]
m = dict(policy_version="1.0.0", go_tag="uix-7-android-cashier-experience-remediation-go",
         candidate_commit=SHA, app_source_unchanged_since=SHA,
         decision="GO — complete", scenarios=scen)

if case == "complete":
    pass
elif case == "emu_bt_reject":            # emulator PASS on Bluetooth printer (hw_dependent)
    m["scenarios"][2] = row(scenario_id="H02", classification="hardware_dependent",
        evidence_source="emulator")
elif case == "emu_nfc_reject":           # emulator PASS on NFC (hw_dependent)
    m["scenarios"][2] = row(scenario_id="H03", classification="hardware_dependent",
        evidence_source="emulator")
elif case == "missing_checksum":
    m["scenarios"][1] = row(scenario_id="R11", classification="hardware_independent",
        evidence_source="emulator", apk_sha256="")
elif case == "missing_source":
    m["scenarios"][1] = row(scenario_id="R11", classification="hardware_independent",
        evidence_source="pending")      # PASS row cannot be 'pending'
elif case == "stale_commit":
    m["scenarios"][1] = row(scenario_id="R11", classification="hardware_independent",
        evidence_source="emulator", commit_sha="deadbee")  # != candidate/unchanged
elif case == "empty_scenarios":
    m["scenarios"] = []
elif case == "invalid_na":               # N/A with no domain reason
    m["scenarios"][2] = row(scenario_id="H02", classification="hardware_dependent",
        result="N/A", evidence_reference="")
elif case == "blocker":                  # a PENDING row remains
    m["scenarios"].append(row(scenario_id="R12", classification="hardware_independent",
        evidence_source="pending", result="PENDING", commit_sha="", app_version="",
        apk_sha256="", build_variant="", environment="", executed_at="",
        verification_method="", evidence_reference="awaiting capture"))
    m["decision"] = "NO-GO — GO DEFERRED"
elif case == "not_structured":
    with open(out, "w") as f: f.write("PASS PASS PASS all good\n")  # not JSON
    raise SystemExit
with open(out, "w") as f: json.dump(m, f, indent=2)
PY
  echo "$out"
}

TRUE_ENV=(UIX7_CI_GREEN=true UIX7_PR_MERGED=true UIX7_EXACT_MATCH=true UIX7_GO_TAG_COMMIT_OVERRIDE=abc1234)

# --- baselines against the real, honest manifest ---
check "real manifest preflight" pass preflight "$REAL"
check "real manifest closure blocked (blockers remain)" fail closure "$REAL" "${TRUE_ENV[@]}"

# 1 & 2. physical + emulator(hw-independent) PASS accepted; complete closure PASS.
check "complete manifest preflight" pass preflight "$(fixture complete)"
check "complete manifest closure PASS" pass closure "$(fixture complete)" "${TRUE_ENV[@]}"

# 3 & 4. emulator PASS rejected for hardware-required rows.
check "emulator rejected for bluetooth printer" fail preflight "$(fixture emu_bt_reject)"
check "emulator rejected for NFC"                fail preflight "$(fixture emu_nfc_reject)"

# 5. missing APK checksum rejected.
check "missing checksum rejected" fail preflight "$(fixture missing_checksum)"

# 6 & 9. wrong/stale commit rejected.
check "stale/wrong commit rejected" fail preflight "$(fixture stale_commit)"

# 7. missing evidence source rejected.
check "missing source rejected" fail preflight "$(fixture missing_source)"

# 8. plain 'PASS' without structured data rejected.
check "non-structured PASS rejected" fail preflight "$(fixture not_structured)"

# 10. incomplete/empty scenario set rejected.
check "empty scenario set rejected" fail preflight "$(fixture empty_scenarios)"

# 11. invalid N/A (no reason) rejected.
check "invalid N/A rejected" fail preflight "$(fixture invalid_na)"

# 12. unresolved blocker rejected in closure.
check "unresolved blocker rejected (closure)" fail closure "$(fixture blocker)" "${TRUE_ENV[@]}"

# 13. exact-match failure rejected.
check "exact-match failure rejected" fail closure "$(fixture complete)" \
  UIX7_CI_GREEN=true UIX7_PR_MERGED=true UIX7_EXACT_MATCH=false UIX7_GO_TAG_COMMIT_OVERRIDE=abc1234

# 14. closure before merge rejected.
check "closure before merge rejected" fail closure "$(fixture complete)" \
  UIX7_CI_GREEN=true UIX7_PR_MERGED=false UIX7_EXACT_MATCH=true UIX7_GO_TAG_COMMIT_OVERRIDE=abc1234

# 15. target tag mismatch rejected.
check "target tag/commit mismatch rejected" fail closure "$(fixture complete)" \
  UIX7_CI_GREEN=true UIX7_PR_MERGED=true UIX7_EXACT_MATCH=true UIX7_GO_TAG_COMMIT_OVERRIDE=deadbeef

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ] || exit 1
