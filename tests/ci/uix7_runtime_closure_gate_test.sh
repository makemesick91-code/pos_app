#!/usr/bin/env bash
#
# Self-contained fail-closed tests for scripts/uix7_runtime_closure_gate.sh
# (UIX7-R052..R070). No bats dependency. Proves the gate:
#   * PASSes structurally against the real evidence doc,
#   * FAILs closed on a premature GO, an unreferenced PASS, and a leaked secret,
#   * FAILs in closure mode while PENDING rows remain,
#   * PASSes in closure mode only when the doc is genuinely complete.
#
# Usage: bash tests/ci/uix7_runtime_closure_gate_test.sh
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"; cd "$ROOT"
GATE="$ROOT/scripts/uix7_runtime_closure_gate.sh"
REAL="docs/deployment/uix-7-physical-device-runtime-closure.md"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
PASS=0; FAIL=0

# check <name> <expected: pass|fail> <mode> <doc>
check() {
  local name="$1" expect="$2" mode="$3" doc="$4" rc
  UIX7_CLOSURE_GATE_MODE="$mode" UIX7_CLOSURE_DOC="$doc" bash "$GATE" >/dev/null 2>&1; rc=$?
  local got="pass"; [ "$rc" -ne 0 ] && got="fail"
  if [ "$got" = "$expect" ]; then PASS=$((PASS+1)); printf 'ok   - %s (%s)\n' "$name" "$got"
  else FAIL=$((FAIL+1)); printf 'FAIL - %s : expected %s got %s (rc=%d)\n' "$name" "$expect" "$got" "$rc"; fi
}

# 1. Real doc, structural → PASS.
check "real doc structural" pass structural "$REAL"

# 2. Real doc, closure → FAIL (runtime rows still PENDING).
check "real doc closure blocked" fail closure "$REAL"

# 3. Premature GO: declare GO while PENDING rows remain → FAIL (safety invariant).
sed 's/^Decision: \*\*NO-GO.*/Decision: **GO**/' "$REAL" > "$TMP/premature-go.md"
check "premature GO blocked" fail structural "$TMP/premature-go.md"

# 4. PASS row without an evidence reference → FAIL.
cp "$REAL" "$TMP/no-evidence.md"
printf '| 99 | Synthetic extra check | UIX7-R052 | PASS |  |\n' >> "$TMP/no-evidence.md"
check "unreferenced PASS blocked" fail structural "$TMP/no-evidence.md"

# 5. Leaked secret value → FAIL.
cp "$REAL" "$TMP/secret.md"
printf '\nbearer aBcD1234EfGh5678\n' >> "$TMP/secret.md"
check "leaked secret blocked" fail structural "$TMP/secret.md"

# 6. Genuinely complete doc → closure PASS. Flip every PENDING to PASS with a
#    reference, and set the decision to GO.
sed -e 's/| PENDING |/| PASS |/g' \
    -e 's/| PENDING /| PASS /g' \
    -e 's/^Decision: \*\*NO-GO.*/Decision: **GO** — all physical-device runtime evidence captured./' \
    "$REAL" > "$TMP/complete.md"
# Give the cleanup/vps/dms/exact-match tables (| item | PASS | — |) real refs.
sed -i 's/| PASS | — |/| PASS | evidence captured |/g; s/| PASS | -- |/| PASS | evidence captured |/g' "$TMP/complete.md"
check "complete doc closure PASS" pass closure "$TMP/complete.md"

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ] || exit 1
