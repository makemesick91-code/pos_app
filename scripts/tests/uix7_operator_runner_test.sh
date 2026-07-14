#!/usr/bin/env bash
# Regression tests for scripts/uix7_operator_runner.sh — proves the physical-
# device operator runner is fail-closed, UIX-7-schema-native, uses an EXPLICIT
# device serial for every adb call, normalizes physical evidence, keeps the
# candidate/source-anchor separation, and never fabricates a PASS.
#
# Runs WITHOUT an Android device: adb is either skipped (UIX7_OP_SKIP_ADB=1) or
# replaced by a recording fake. The real manifest is never mutated.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"; cd "$ROOT"
RUNNER=scripts/uix7_operator_runner.sh
GATE=scripts/uix7_runtime_closure_gate.sh
REAL=docs/deployment/uix-7-runtime-evidence.json
ANCHOR=97fbb64
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
fails=0
ok(){ printf '  [PASS] %s\n' "$1"; }; no(){ printf '  [FAIL] %s\n' "$1"; fails=1; }

# Fixtures.
FAKE_APK="$TMP/app.apk"; head -c 4096 /dev/urandom > "$FAKE_APK"
APK_SHA="$(sha256sum "$FAKE_APK" | awk '{print $1}')"
SHOT="$TMP/shot.png"; printf '\x89PNG\r\n\x1a\n........' > "$SHOT"     # valid PNG magic
BADSHOT="$TMP/bad.png"; printf 'not-a-real-png-file' > "$BADSHOT"     # wrong magic
SESSION="$TMP/session.json"
MAN="$TMP/manifest.json"; cp "$REAL" "$MAN"
RUNID="run-test-fixed"
OFFCREF="cref-off-$RUNID"; ONCREF="cref-on-$RUNID"

# Recording fake adb (device-free): logs args, answers the runner's probes.
FADB="$TMP/fake-adb"; ADBLOG="$TMP/adb.log"
cat > "$FADB" <<'EOS'
#!/usr/bin/env bash
echo "$*" >> "$ADBLOG"
case "$*" in
  *get-state*) echo "${FAKE_STATE:-device}";;
  *getprop*)   echo 34;;
  *"pm path"*) echo "package:/data/app/com.aishtech.poslite/base.apk";;
  *screencap*) printf '\x89PNG\r\n\x1a\n........';;
  *) : ;;
esac
EOS
chmod +x "$FADB"

base_env(){ export UIX7_OP_SKIP_ADB=1 UIX7_OP_SERIAL=FAKESERIAL123 UIX7_OP_SESSION="$SESSION" \
  UIX7_EVIDENCE_MANIFEST="$MAN" UIX7_OP_APK="$FAKE_APK" UIX7_OP_APK_SHA256="$APK_SHA" \
  UIX7_OP_APP_SOURCE_COMMIT="$ANCHOR" UIX7_OP_EVIDENCE_DIR="$TMP/evid" UIX7_OP_RUN_ID="$RUNID"
  unset UIX7_OP_CANDIDATE UIX7_OP_ADB; }
clr(){ unset OP_RESULT OP_OBSERVATION OP_SCREENSHOT OP_TXN_REF OP_CLIENT_REF OP_DB_REF OP_RUN_ID OP_METHOD OP_EVIDENCE_REF; }
open_run(){ base_env; clr; bash "$RUNNER" preflight >/dev/null 2>&1; }
rowstat(){ python3 -c "import json;d=json.load(open('$SESSION'));r=d['scenarios'].get('$1');print(r['status'] if r else 'absent')"; }
jget(){ python3 -c "import json;print(json.load(open('$1')).get('$2'))"; }
# Record a genuine PASS for a scenario, wiring txn/client/db refs by kind.
pass_row(){ # sid extra_obs
  local sid="$1" cref="" txn="" db=""
  case " R03 R04 R05 R06 " in *" $sid "*) cref="$ONCREF"; txn="sale#online/$sid";; esac
  case " R11 R12 R13 R14 R15 R16 R17 " in *" $sid "*) cref="$OFFCREF"; txn="sale#offline/$sid";; esac
  case " R03 R05 R06 R13 R14 R15 R17 " in *" $sid "*) db="sales=1 payments=1 sale_items=2 dup=0 for $sid window";; esac
  clr; base_env
  OP_RESULT=PASS OP_OBSERVATION="physically observed $sid on device: $2 (integer rupiah, no stale data)" \
    OP_SCREENSHOT="$SHOT" OP_TXN_REF="$txn" OP_CLIENT_REF="$cref" OP_DB_REF="$db" \
    bash "$RUNNER" record "$sid" >/dev/null 2>&1
}

# 1. UIX-7 `scenarios` schema is used (top-level scenarios array, scenario_id/result).
if python3 -c "import json,sys;d=json.load(open('$MAN'));sys.exit(0 if isinstance(d.get('scenarios'),list) and 'result' in d['scenarios'][0] and 'scenario_id' in d['scenarios'][0] else 1)"; then ok "manifest uses UIX-7 scenarios/scenario_id/result schema"; else no "manifest not UIX-7 schema"; fi

# 2. UIX-8 rows/id/status schema is rejected (finalize does not create rows/id/status).
open_run; pass_row R01 "device activated"
base_env; bash "$RUNNER" finalize >/dev/null 2>&1
if python3 -c "import json,sys;d=json.load(open('$MAN'));sys.exit(1 if 'rows' in d else 0)" \
   && python3 -c "import json,sys;d=json.load(open('$MAN'));sys.exit(1 if any('id' in s or 'status' in s for s in d['scenarios']) else 0)"; then
  ok "no UIX-8 rows/id/status schema introduced into UIX-7 manifest"; else no "UIX-8 schema leaked into UIX-7 manifest"; fi
cp "$REAL" "$MAN"

# 3. physical input normalizes to physical.
open_run
[ "$(jget "$SESSION" evidence_source)" = "physical" ] && ok "evidence_source normalized to physical" || no "evidence_source not physical"

# 4. an alias like physical_device is never persisted directly (normalized to physical).
base_env; clr; UIX7_OP_EVIDENCE_SOURCE=physical_device bash "$RUNNER" preflight >/dev/null 2>&1
[ "$(jget "$SESSION" evidence_source)" = "physical" ] && ok "physical_device alias normalized, never persisted raw" || no "alias persisted raw"

# 5. missing serial rejected.
base_env; clr; unset UIX7_OP_SERIAL
if bash "$RUNNER" preflight >/dev/null 2>&1; then no "missing serial should fail"; else ok "missing serial rejected"; fi
export UIX7_OP_SERIAL=FAKESERIAL123

# 6. unauthorized / offline device rejected (fake adb, adb NOT skipped).
base_env; clr; unset UIX7_OP_SKIP_ADB
if FAKE_STATE=unauthorized ADBLOG="$ADBLOG" UIX7_OP_ADB="$FADB" bash "$RUNNER" preflight >/dev/null 2>&1; then no "unauthorized device should fail"; else ok "unauthorized device rejected"; fi
if FAKE_STATE=offline ADBLOG="$ADBLOG" UIX7_OP_ADB="$FADB" bash "$RUNNER" preflight >/dev/null 2>&1; then no "offline device should fail"; else ok "offline device rejected"; fi

# 7. every device-targeting adb command uses the explicit serial (static + runtime).
# Device subcommands must route through adbx (which prepends -s "$SERIAL"); no
# direct "$ADB" <device-subcommand> may bypass it.
if grep -nE '"\$ADB"[[:space:]]+(shell|exec-out|get-state|install|push|pull|screencap)' "$RUNNER" | grep -q .; then
  no "a device adb call bypasses the explicit-serial helper"
elif grep -q 'adbx(){ "\$ADB" -s "\$SERIAL"' "$RUNNER"; then
  ok "static: device adb calls route through adbx (-s serial)"
else
  no "adbx does not prepend -s serial"; fi
: > "$ADBLOG"; base_env; clr; unset UIX7_OP_SKIP_ADB
FAKE_STATE=device ADBLOG="$ADBLOG" UIX7_OP_ADB="$FADB" bash "$RUNNER" preflight >/dev/null 2>&1
FAKE_STATE=device ADBLOG="$ADBLOG" UIX7_OP_ADB="$FADB" OP_RESULT=PENDING OP_OBSERVATION="probing device screenshot path" bash "$RUNNER" record R01 >/dev/null 2>&1
if [ -s "$ADBLOG" ] && ! grep -qv '^-s FAKESERIAL123' "$ADBLOG"; then ok "runtime: all adb invocations addressed the explicit serial"; else no "an adb invocation did not use the explicit serial"; fi
export UIX7_OP_SKIP_ADB=1

# 8. raw serial not persisted (only a 12-char hash).
open_run
if grep -q 'FAKESERIAL123' "$SESSION"; then no "raw serial persisted in session"; else ok "raw serial not persisted"; fi
[ "$(jget "$SESSION" device_serial_hash | wc -c)" -eq 13 ] && ok "serial persisted as short hash" || no "serial hash malformed"

# 9. APK checksum mismatch rejected.
base_env; clr
if UIX7_OP_APK_SHA256=deadbeef bash "$RUNNER" preflight >/dev/null 2>&1; then no "checksum mismatch should fail"; else ok "APK checksum mismatch rejected (UIX7-R076)"; fi

# 10. package mismatch rejected (fake adb reports no package path).
NOPKG="$TMP/fake-adb-nopkg"
cat > "$NOPKG" <<'EOS'
#!/usr/bin/env bash
case "$*" in
  *get-state*) echo device;;
  *getprop*) echo 34;;
  *"pm path"*) : ;;   # package NOT installed
  *) : ;;
esac
EOS
chmod +x "$NOPKG"
base_env; clr; unset UIX7_OP_SKIP_ADB
if UIX7_OP_ADB="$NOPKG" ADBLOG="$ADBLOG" bash "$RUNNER" preflight >/dev/null 2>&1; then no "package mismatch should fail"; else ok "package-not-installed rejected"; fi
export UIX7_OP_SKIP_ADB=1

# 11. generic observation rejected (downgraded to PENDING).
open_run; clr; base_env; OP_RESULT=PASS OP_OBSERVATION="ok" OP_SCREENSHOT="$SHOT" bash "$RUNNER" record R01 >/dev/null 2>&1
[ "$(rowstat R01)" = "PENDING" ] && ok "generic observation downgraded to PENDING" || no "generic observation not downgraded"

# 12. missing screenshot rejected.
open_run; clr; base_env; OP_RESULT=PASS OP_OBSERVATION="device activation showed ACTIVE state bound to tenant" bash "$RUNNER" record R01 >/dev/null 2>&1
[ "$(rowstat R01)" = "PENDING" ] && ok "missing screenshot downgraded" || no "missing screenshot not downgraded"

# 13. empty/non-PNG screenshot rejected.
open_run; clr; base_env; OP_RESULT=PASS OP_OBSERVATION="device activation showed ACTIVE state bound to tenant" OP_SCREENSHOT="$BADSHOT" bash "$RUNNER" record R01 >/dev/null 2>&1
[ "$(rowstat R01)" = "PENDING" ] && ok "non-PNG screenshot downgraded" || no "non-PNG screenshot not downgraded"

# 14. invalid scenario id rejected.
open_run; clr; base_env
if OP_RESULT=PENDING OP_OBSERVATION="x" bash "$RUNNER" record R99 >/dev/null 2>&1; then no "unknown scenario should fail"; else ok "unknown scenario id rejected"; fi

# 15. H01–H04/Q01 mutation rejected.
open_run; clr; base_env
if OP_RESULT=PENDING OP_OBSERVATION="x" bash "$RUNNER" record H01 >/dev/null 2>&1; then no "H01 mutation should fail"; else ok "protected H01 rejected"; fi
if OP_RESULT=PENDING OP_OBSERVATION="x" bash "$RUNNER" record Q01 >/dev/null 2>&1; then no "Q01 mutation should fail"; else ok "protected Q01 rejected"; fi

# 16. R02 blocked before R01.
open_run; clr; base_env
if OP_RESULT=PASS OP_OBSERVATION="tenant/outlet/role binding correct" OP_SCREENSHOT="$SHOT" bash "$RUNNER" record R02 >/dev/null 2>&1; then no "R02 should block before R01"; else ok "R02 blocked before R01"; fi

# 17. R03 blocked before R02.
open_run; pass_row R01 "device activated"
clr; base_env
if OP_RESULT=PASS OP_OBSERVATION="one backend sale" OP_SCREENSHOT="$SHOT" OP_TXN_REF="sale#3" OP_CLIENT_REF="$ONCREF" OP_DB_REF="sales=1" bash "$RUNNER" record R03 >/dev/null 2>&1; then no "R03 should block before R02"; else ok "R03 blocked before R02"; fi

# 18. R12 blocked before R11.
open_run; clr; base_env
if OP_RESULT=PASS OP_OBSERVATION="pending txn survived force-stop" OP_SCREENSHOT="$SHOT" OP_TXN_REF="sale#off" OP_CLIENT_REF="$OFFCREF" bash "$RUNNER" record R12 >/dev/null 2>&1; then no "R12 should block before R11"; else ok "R12 blocked before R11"; fi

# 19. R13 blocked before R12.
open_run; pass_row R11 "offline durable save confirmed before cart cleared"
clr; base_env
if OP_RESULT=PASS OP_OBSERVATION="reconnect synced idempotently" OP_SCREENSHOT="$SHOT" OP_TXN_REF="sale#off" OP_CLIENT_REF="$OFFCREF" OP_DB_REF="sales=1" bash "$RUNNER" record R13 >/dev/null 2>&1; then no "R13 should block before R12"; else ok "R13 blocked before R12"; fi

# 20. mismatched run id rejected.
open_run; pass_row R01 "device activated"
clr; base_env
if OP_RUN_ID="run-wrong" OP_RESULT=PASS OP_OBSERVATION="tenant binding correct" OP_SCREENSHOT="$SHOT" bash "$RUNNER" record R02 >/dev/null 2>&1; then no "mismatched run id should fail"; else ok "mismatched run id rejected"; fi

# 21. mismatched client reference rejected.
open_run; pass_row R01 "device activated"; pass_row R02 "tenant/outlet/role binding correct"
clr; base_env
if OP_RESULT=PASS OP_OBSERVATION="one backend sale created" OP_SCREENSHOT="$SHOT" OP_TXN_REF="sale#3" OP_CLIENT_REF="cref-WRONG" OP_DB_REF="sales=1" bash "$RUNNER" record R03 >/dev/null 2>&1; then no "mismatched client ref should fail"; else ok "mismatched client reference rejected"; fi

# 22. missing DB evidence rejected for a DB-required row (downgraded to PENDING).
open_run; pass_row R01 "device activated"; pass_row R02 "tenant/outlet/role binding correct"
clr; base_env; OP_RESULT=PASS OP_OBSERVATION="one backend sale created for this checkout" OP_SCREENSHOT="$SHOT" OP_TXN_REF="sale#3" OP_CLIENT_REF="$ONCREF" bash "$RUNNER" record R03 >/dev/null 2>&1
[ "$(rowstat R03)" = "PENDING" ] && ok "DB-required row without DB ref downgraded" || no "missing DB ref not downgraded"

# 23. record does not mutate the canonical manifest.
BEFORE="$(sha256sum "$MAN" | awk '{print $1}')"
open_run; pass_row R01 "device activated"
AFTER="$(sha256sum "$MAN" | awk '{print $1}')"
[ "$BEFORE" = "$AFTER" ] && ok "record does not mutate the manifest" || no "record mutated the manifest"

# 24. finalize with PENDING keeps NO-GO.
cp "$REAL" "$MAN"; open_run; pass_row R01 "device activated"
base_env; bash "$RUNNER" finalize >/dev/null 2>&1
case "$(jget "$MAN" decision)" in NO-GO*) ok "finalize with PENDING keeps NO-GO — GO DEFERRED";; *) no "finalize wrongly changed decision";; esac

# 25. finalize with a complete fixture writes schema-valid physical PASS rows.
cp "$REAL" "$MAN"; open_run
pass_row R01 "device activated ACTIVE"; pass_row R02 "tenant/outlet/role binding correct"
pass_row R03 "exactly one backend sale"; pass_row R04 "cart=subtotal=grand=receipt parity"
pass_row R05 "stable client_reference reused"; pass_row R06 "rapid tap yields one sale"
pass_row R11 "offline durable save before cart clear"; pass_row R12 "pending txn survived force-stop"
pass_row R13 "reconnect idempotent sync no dup"; pass_row R14 "SYNCED only after server ack"
pass_row R15 "sales=1 payments=1 no duplicates"; pass_row R16 "receipt bound to current txn"
pass_row R17 "receipt/history/backend parity"; pass_row R18 "TalkBack labels + 130% font ok"
pass_row R19 "no FATAL/ANR over the window"; pass_row R20 "no cleartext/secret in logs"
base_env; bash "$RUNNER" finalize >/dev/null 2>&1
r01src="$(python3 -c "import json;print([s for s in json.load(open('$MAN'))['scenarios'] if s['scenario_id']=='R11'][0]['evidence_source'])")"
r11commit="$(python3 -c "import json;print([s for s in json.load(open('$MAN'))['scenarios'] if s['scenario_id']=='R11'][0]['commit_sha'])")"
allpass="$(python3 -c "import json;d=json.load(open('$MAN'));ids=set('R01 R02 R03 R04 R05 R06 R11 R12 R13 R14 R15 R16 R17 R18 R19 R20'.split());print(all(s['result']=='PASS' for s in d['scenarios'] if s['scenario_id'] in ids))")"
[ "$allpass" = "True" ] && [ "$r01src" = "physical" ] && [ "$r11commit" = "$ANCHOR" ] && ok "finalize wrote physical PASS rows bound to the anchor" || no "finalize rows not schema-valid (allpass=$allpass src=$r01src commit=$r11commit)"
# and the closure gate accepts the finalized manifest at preflight (schema-valid).
if UIX7_CLOSURE_GATE_MODE=preflight UIX7_EVIDENCE_MANIFEST="$MAN" bash "$GATE" >/dev/null 2>&1; then ok "closure gate preflight accepts finalized manifest"; else no "closure gate rejected finalized manifest"; fi

# 26. historical / protected scenarios preserved through finalize.
na_ok="$(python3 -c "import json;d=json.load(open('$MAN'));ids=set('H01 H02 H03 H04 Q01'.split());print(all(s['result']=='N/A' for s in d['scenarios'] if s['scenario_id'] in ids))")"
[ "$na_ok" = "True" ] && ok "H01–H04/Q01 preserved as N/A after finalize" || no "protected scenarios mutated"

# 27. candidate and app-source anchor treated separately.
anc="$(jget "$MAN" app_source_unchanged_since)"; cand="$(jget "$MAN" candidate_commit)"
[ "$anc" = "$ANCHOR" ] && [ "$cand" = "None" ] && ok "anchor set to runtime source; candidate NOT set to HEAD" || no "anchor/candidate not separated (anc=$anc cand=$cand)"
# When a real closure candidate IS supplied, only then does candidate_commit populate.
cp "$REAL" "$MAN"; base_env; clr; UIX7_OP_CANDIDATE="$(git rev-parse HEAD)" bash "$RUNNER" preflight >/dev/null 2>&1
pass_row R01 "device activated"; UIX7_OP_CANDIDATE="$(git rev-parse HEAD)" base_env >/dev/null 2>&1
base_env; UIX7_OP_CANDIDATE="$(git rev-parse HEAD)" bash "$RUNNER" finalize >/dev/null 2>&1
[ "$(jget "$MAN" candidate_commit)" = "$(git rev-parse HEAD)" ] && ok "explicit candidate populates candidate_commit only when supplied" || no "explicit candidate not honored"
cp "$REAL" "$MAN"

# 28. runtime source ancestor validation works.
base_env; clr
if UIX7_OP_APP_SOURCE_COMMIT=deadbeefdeadbeef bash "$RUNNER" preflight >/dev/null 2>&1; then no "bogus anchor should fail"; else ok "non-commit anchor rejected"; fi
NONANC="$(git commit-tree "$(git rev-parse HEAD^{tree})" -m throwaway </dev/null)"
if UIX7_OP_APP_SOURCE_COMMIT="$NONANC" bash "$RUNNER" preflight >/dev/null 2>&1; then no "non-ancestor anchor should fail"; else ok "non-ancestor anchor rejected"; fi
base_env; clr; bash "$RUNNER" preflight >/dev/null 2>&1 && ok "real ancestor anchor accepted" || no "real ancestor anchor rejected"

# 29. raw secret in observation rejected (never persisted).
open_run; clr; base_env
if OP_RESULT=PENDING OP_OBSERVATION="saw home screen; Authorization: Bearer eyJhbGZ.abc.def token in logs" OP_SCREENSHOT="$SHOT" bash "$RUNNER" record R01 >/dev/null 2>&1; then no "secret in observation should be rejected"; else ok "secret in observation rejected"; fi
grep -q 'eyJhbGZ' "$SESSION" 2>/dev/null && no "secret leaked into session" || ok "secret not persisted to session"

# 30. closure gate remains unchanged and fail-closed.
if git diff --quiet -- "$GATE"; then ok "closure gate script unchanged by this work"; else no "closure gate was modified"; fi
UIX7_CLOSURE_GATE_MODE=preflight UIX7_EVIDENCE_MANIFEST="$REAL" bash "$GATE" >/dev/null 2>&1 && ok "closure gate preflight passes on real manifest" || no "closure gate preflight failed on real manifest"
if UIX7_CLOSURE_GATE_MODE=closure UIX7_EVIDENCE_MANIFEST="$REAL" UIX7_CI_GREEN=true UIX7_PR_MERGED=true UIX7_EXACT_MATCH=true bash "$GATE" >/dev/null 2>&1; then no "closure should fail on real (blockers remain)"; else ok "closure gate stays fail-closed on real manifest"; fi

[ "$fails" = 0 ] && { echo "uix7 operator-runner tests: ALL PASS"; exit 0; } || { echo "uix7 operator-runner tests: FAILURES"; exit 1; }
