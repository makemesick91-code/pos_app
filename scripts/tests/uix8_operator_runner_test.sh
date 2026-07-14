#!/usr/bin/env bash
# Regression tests for scripts/uix8_operator_runner.sh — proves the runner is
# fail-closed and never fabricates a PASS (rule 59 UIX8BOPS-R023..R058).
# Runs without an emulator (UIX8_OP_SKIP_ADB=1) and without touching the real manifest.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"; cd "$ROOT"
RUNNER=scripts/uix8_operator_runner.sh
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
fails=0
ok(){ printf '  [PASS] %s\n' "$1"; }; no(){ printf '  [FAIL] %s\n' "$1"; fails=1; }

# Fake APK + a matching sha, plus a fake screenshot.
FAKE_APK="$TMP/app.apk"; head -c 4096 /dev/urandom > "$FAKE_APK"
APK_SHA="$(sha256sum "$FAKE_APK" | awk '{print $1}')"
SHOT="$TMP/shot.png"; echo x > "$SHOT"
HEAD="$(git rev-parse HEAD)"
SESSION="$TMP/session.json"
MAN="$TMP/manifest.json"; cp docs/deployment/uix-8-runtime-evidence.json "$MAN"

base_env(){ export UIX8_OP_SKIP_ADB=1 UIX8_OP_SESSION="$SESSION" UIX8_EVIDENCE_MANIFEST="$MAN" \
  UIX8_OP_APK="$FAKE_APK" UIX8_OP_CANDIDATE="$HEAD" UIX8_OP_EVIDENCE_DIR="$TMP/evid"; }
clr(){ unset OP_RESULT OP_OBSERVATION OP_SCREENSHOT OP_TXN_REF; }
open_run(){ base_env; UIX8_OP_APK_SHA256="$APK_SHA" bash "$RUNNER" preflight >/dev/null 2>&1; }
rowstat(){ python3 -c "import json,sys;d=json.load(open('$SESSION'));r=d['rows'].get('$1');print(r['status'] if r else 'absent')"; }

# 1. Checksum mismatch -> preflight fails closed.
base_env
if UIX8_OP_APK_SHA256="deadbeef" bash "$RUNNER" preflight >/dev/null 2>&1; then no "checksum mismatch should fail preflight"; else ok "checksum mismatch rejected (UIX8BOPS-R024)"; fi

# 2. Wrong candidate -> preflight fails closed.
base_env
if UIX8_OP_APK_SHA256="$APK_SHA" UIX8_OP_CANDIDATE="0000000000000000000000000000000000000000" bash "$RUNNER" preflight >/dev/null 2>&1; then no "wrong candidate should fail"; else ok "non-candidate HEAD rejected (UIX8BOPS-R023)"; fi

# 3. Blank result -> PENDING.
open_run; clr; base_env; OP_OBSERVATION="saw the cart persist across rotation" OP_SCREENSHOT="$SHOT" bash "$RUNNER" record cart-operations >/dev/null 2>&1
[ "$(rowstat cart-operations)" = "PENDING" ] && ok "blank result stays PENDING (UIX8BOPS-R030)" || no "blank result not PENDING"

# 4. Generic observation with PASS -> downgraded to PENDING.
open_run; clr; base_env; OP_RESULT=PASS OP_OBSERVATION="ok" OP_SCREENSHOT="$SHOT" bash "$RUNNER" record cart-operations >/dev/null 2>&1
[ "$(rowstat cart-operations)" = "PENDING" ] && ok "generic observation downgraded (UIX8BOPS-R031/R032)" || no "generic observation not downgraded"

# 5. Substantive obs but no screenshot -> PENDING.
open_run; clr; base_env; OP_RESULT=PASS OP_OBSERVATION="rotation preserved all three cart lines and total" bash "$RUNNER" record cart-operations >/dev/null 2>&1
[ "$(rowstat cart-operations)" = "PENDING" ] && ok "missing screenshot downgraded (UIX8BOPS-R029/R033)" || no "missing screenshot not downgraded"

# 6. Transaction row PASS without txn reference -> PENDING.
open_run; clr; base_env; OP_RESULT=PASS OP_OBSERVATION="offline sale saved durably, cart cleared after save" OP_SCREENSHOT="$SHOT" bash "$RUNNER" record offline-checkout >/dev/null 2>&1
[ "$(rowstat offline-checkout)" = "PENDING" ] && ok "txn row without reference downgraded (UIX8BOPS-R034)" || no "txn row without ref not downgraded"

# 7. Dependency: force-stop-restoration before offline-checkout PASS -> blocked.
open_run; clr; base_env
if OP_RESULT=PASS OP_OBSERVATION="reopened app shows same pending sale after force stop" OP_SCREENSHOT="$SHOT" OP_TXN_REF="sale#42" bash "$RUNNER" record force-stop-restoration >/dev/null 2>&1; then no "dependency should block"; else ok "unmet dependency blocked (UIX8BOPS-R028)"; fi

# 8. Happy path: full genuine PASS for offline-checkout.
open_run; clr; base_env; OP_RESULT=PASS OP_OBSERVATION="offline sale persisted to Room, cart cleared only after durable save confirmed" OP_SCREENSHOT="$SHOT" OP_TXN_REF="sale#42/cref" bash "$RUNNER" record offline-checkout >/dev/null 2>&1
[ "$(rowstat offline-checkout)" = "PASS" ] && ok "genuine PASS recorded" || no "genuine PASS not recorded"

# 9. Now the dependent row is allowed once prerequisite is PASS.
clr; base_env; OP_RESULT=PASS OP_OBSERVATION="after force-stop and relaunch the same pending sale is restored intact" OP_SCREENSHOT="$SHOT" OP_TXN_REF="sale#42/cref" bash "$RUNNER" record force-stop-restoration >/dev/null 2>&1
[ "$(rowstat force-stop-restoration)" = "PASS" ] && ok "dependent row passes after prerequisite (UIX8BOPS-R028)" || no "dependent row not allowed after prereq"

# 10. finalize with remaining PENDING rows keeps decision GO_DEFERRED (fail-closed).
base_env; bash "$RUNNER" finalize >/dev/null 2>&1
dec="$(python3 -c "import json;print(json.load(open('$MAN'))['decision'])")"
[ "$dec" = "GO_DEFERRED" ] && ok "finalize keeps GO_DEFERRED while rows PENDING (UIX8BOPS-R066/R070)" || no "finalize wrongly set $dec"

# 11. candidate/apk binding written from session, not blank (UIX8BOPS-R023/R024).
cc="$(python3 -c "import json;print(json.load(open('$MAN'))['candidate_commit'])")"
[ "$cc" = "$HEAD" ] && ok "manifest candidate bound from session" || no "candidate not bound ($cc)"

[ "$fails" = 0 ] && { echo "operator-runner tests: ALL PASS"; exit 0; } || { echo "operator-runner tests: FAILURES"; exit 1; }
