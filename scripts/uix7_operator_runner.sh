#!/usr/bin/env bash
# UIX-7 — Fail-closed PHYSICAL-DEVICE operator runtime-evidence runner.
#
# Captures GENUINE operator-observed PHYSICAL-DEVICE evidence for a fresh
# current-descendant revalidation of the UIX-7 cashier runtime scenarios
# R01–R06 and R11–R20, then (only) merges genuinely-PASSed rows into the
# STRUCTURED UIX-7 manifest. It NEVER fabricates a PASS: a blank/generic
# observation, a missing/empty/non-PNG screenshot, a missing transaction or DB
# reference (where required), a secret in the observation, or an unmet
# dependency all stay PENDING (or are rejected outright). Operator observation
# is a human checkpoint (rule 55 UIX7-R052..R080, rule 59 UIX8BOPS-R023..R058).
#
# This runner is UIX-7-schema-native: the manifest uses a top-level `scenarios`
# array with `scenario_id` + `result`. It NEVER introduces the UIX-8
# `rows`/`id`/`status` schema into UIX-7, and it persists evidence_source
# exactly as "physical" (the UIX-7 enum has no "operator"/"physical_device").
#
# Unlike the UIX-8 runner, this runner NEVER selects the first adb device: it
# requires an explicit UIX7_OP_SERIAL and addresses every adb call with
# `adb -s "$serial"`. Only the SHA-256-derived serial hash is persisted.
#
# Candidate vs source anchor: the runtime APK is built from an app-source anchor
# (default 97fbb64) that is an ancestor of — and may differ from — current HEAD
# through evidence/tooling/docs commits. The runner records both separately and
# never overwrites `app_source_unchanged_since` with current HEAD.
#
# Subcommands:
#   preflight            Validate serial + APK SHA-256 + package + device + anchor; open a run.
#   record <scenario_id> Capture one scenario (auto-screenshot via adb) fail-closed.
#   status               Show the current run's captured scenarios.
#   finalize             Merge genuinely-PASSed scenarios into the manifest (schema-valid).
#
# Non-interactive inputs (used by tests / automation of the OBSERVATION step only):
#   OP_RESULT=PASS|FAIL|PENDING   OP_OBSERVATION="..."   OP_SCREENSHOT=/path.png
#   OP_TXN_REF="sale#/txn"  OP_CLIENT_REF="cref..."  OP_DB_REF="sanitized aggregates"
#   OP_METHOD="..."  OP_EVIDENCE_REF="..."  OP_RUN_ID="..."  OP_EXECUTED_AT="YYYY-MM-DD"
# Env (see runbook docs/deployment/uix-7-physical-device-operator-runbook.md):
#   UIX7_EVIDENCE_MANIFEST   manifest path (default docs/deployment/uix-7-runtime-evidence.json)
#   UIX7_OP_SERIAL           REQUIRED physical device serial (never persisted raw)
#   UIX7_OP_EVIDENCE_SOURCE  operator alias (physical/physical_device/...); normalized to "physical"
#   UIX7_OP_RUN_ID           campaign run id (default derived, deterministic)
#   UIX7_OP_APK              APK path (default android/app/build/outputs/apk/pilot/app-pilot.apk)
#   UIX7_OP_APK_SHA256       expected APK sha256 (verified in preflight)
#   UIX7_OP_APP_SOURCE_COMMIT runtime app-source anchor (default 97fbb64; ancestor of HEAD)
#   UIX7_OP_CANDIDATE        final evidence-closure candidate commit (optional; NOT auto HEAD)
#   UIX7_OP_EVIDENCE_DIR     evidence dir (default ~/Downloads/AishPOS-UIX7-Physical-Runtime-Closure)
#   UIX7_OP_SESSION          session json path (default under evidence dir)
#   UIX7_OP_OPERATOR         operator name/id (recorded, non-secret)
#   UIX7_OP_SKIP_ADB=1       skip adb device/package/screenshot (tests / dry runs)
#   UIX7_OP_ADB              adb binary path (default "adb"; overridable for tests)
#   UIX7_OP_APP_VERSION      app versionName (default 0.1.0)
#   UIX7_OP_BUILD_VARIANT    build variant (default pilot)
#   UIX7_OP_ENVIRONMENT      physical environment string (model/OS, never the raw serial)
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"

PKG="com.aishtech.poslite"
MIN_SDK=26
MIN_OBS_LEN=12

MANIFEST="${UIX7_EVIDENCE_MANIFEST:-docs/deployment/uix-7-runtime-evidence.json}"
APK="${UIX7_OP_APK:-android/app/build/outputs/apk/pilot/app-pilot.apk}"
APP_SOURCE_COMMIT="${UIX7_OP_APP_SOURCE_COMMIT:-97fbb64}"
CANDIDATE="${UIX7_OP_CANDIDATE:-}"
EVID_DIR="${UIX7_OP_EVIDENCE_DIR:-$HOME/Downloads/AishPOS-UIX7-Physical-Runtime-Closure}"
SESSION="${UIX7_OP_SESSION:-$EVID_DIR/manifest/session-current.json}"
SKIP_ADB="${UIX7_OP_SKIP_ADB:-0}"
ADB="${UIX7_OP_ADB:-adb}"
APP_VERSION="${UIX7_OP_APP_VERSION:-0.1.0}"
BUILD_VARIANT="${UIX7_OP_BUILD_VARIANT:-pilot}"
ENVIRONMENT="${UIX7_OP_ENVIRONMENT:-Xiaomi 2311DRK48G (duchamp), Android 14 / API 34, arm64-v8a}"

red(){ printf '\033[31m%s\033[0m\n' "$1"; }; grn(){ printf '\033[32m%s\033[0m\n' "$1"; }
die(){ red "FAIL: $1"; exit 1; }
lower(){ printf '%s' "$1" | tr '[:upper:]' '[:lower:]'; }

# --- scenario sets ---------------------------------------------------------
# Only these scenario ids are recordable by this runner. H01–H04 and Q01 are
# hardware/QRIS scenarios and MUST NOT be mutated here.
RECORDABLE="R01 R02 R03 R04 R05 R06 R11 R12 R13 R14 R15 R16 R17 R18 R19 R20"
PROTECTED="H01 H02 H03 H04 Q01"
# Transaction rows require a txn reference + a shared clientReference to PASS.
TXN_ROWS="R03 R04 R05 R06 R11 R12 R13 R14 R15 R16 R17"
# Rows that additionally require a sanitized DB evidence reference to PASS.
DB_ROWS="R03 R05 R06 R13 R14 R15 R17"
# Online transaction chain (share the online clientReference).
ONLINE_ROWS="R03 R04 R05 R06"
# Offline transaction chain (share the offline clientReference).
OFFLINE_ROWS="R11 R12 R13 R14 R15 R16 R17"

in_set(){ case " $2 " in *" $1 "*) return 0;; *) return 1;; esac; }
is_txn_row(){ in_set "$1" "$TXN_ROWS"; }
is_db_row(){ in_set "$1" "$DB_ROWS"; }

# Dependency chain: a row's prerequisite must already be PASS before it can PASS.
prereq_of(){ case "$1" in
  R02) echo R01;; R03) echo R02;; R04) echo R03;; R05) echo R04;; R06) echo R05;;
  R12) echo R11;; R13) echo R12;; R14) echo R13;; R15) echo R14;; R16) echo R15;; R17) echo R16;;
  R19) echo R18;; R20) echo R19;;
  *) echo "";; esac; }

# Which shared clientReference a transaction row belongs to.
expected_cref(){
  if in_set "$1" "$ONLINE_ROWS"; then session_get online_client_reference
  elif in_set "$1" "$OFFLINE_ROWS"; then session_get offline_client_reference
  else echo ""; fi; }

# --- normalization / validation helpers ------------------------------------
# The UIX-7 schema enum for evidence_source is:
#   physical | emulator | automated_test | database | ci | vps | pending
# This runner captures PHYSICAL evidence only and always persists "physical".
normalize_source(){
  case "$(lower "$1")" in
    ""|physical|physical_device|physical-device|physicaldevice|operator|operator-observed|operator_observed|device)
      echo physical;;
    *) echo "$(lower "$1")";;  # anything else is surfaced so preflight can reject it
  esac; }

adbx(){ "$ADB" -s "$SERIAL" "$@"; }   # every device-addressed adb call goes through here

is_png(){ # path -> true if a non-empty file with the PNG magic header
  [ -s "$1" ] || return 1
  local hdr; hdr="$(head -c8 "$1" 2>/dev/null | od -An -tx1 2>/dev/null | tr -d ' \n')"
  [ "$hdr" = "89504e470d0a1a0a" ]; }

# Substantive-observation test: length >= MIN and not a bare filler token / the id.
is_substantive(){ # observation scenario_id
  local obs="$1" sid low
  sid="$(lower "$2")"
  low="$(printf '%s' "$obs" | tr '[:upper:]' '[:lower:]' | tr -d '[:space:]')"
  [ "${#low}" -ge "$MIN_OBS_LEN" ] || return 1
  case "$low" in
    pass|ok|okay|good|done|fine|passed|works|working|yes|na|"n/a"|berhasil|sudah|aman|oke|mantap|"$sid") return 1;;
  esac
  return 0; }

# Refuse to persist a secret/credential leaked into the observation (UIX7-R063).
has_secret(){ printf '%s' "$1" | grep -Eiq \
  '(bearer[[:space:]]+[a-z0-9._-]{8,}|authorization[[:space:]]*[:=]|password[[:space:]]*[:=]|passwd[[:space:]]*[:=]|(refresh|access)[_-]?token|api[_-]?key[[:space:]]*[:=]|-----BEGIN [A-Z ]*PRIVATE KEY-----)'; }

# --- python/JSON session helpers -------------------------------------------
_py(){ python3 - "$@"; }

manifest_has(){ # scenario_id -> exit 0 if present in manifest.scenarios
  MANIFEST="$MANIFEST" _py "$1" <<'PY'
import json,os,sys
d=json.load(open(os.environ["MANIFEST"]))
ids={s.get("scenario_id") for s in d.get("scenarios",[])}
sys.exit(0 if sys.argv[1] in ids else 1)
PY
}

session_init(){
  mkdir -p "$(dirname "$SESSION")"
  SESSION="$SESSION" RUN_ID="$RUN_ID" ANCHOR="$APP_SOURCE_COMMIT" REPO="$REPO_COMMIT" \
  CANDIDATE="$CANDIDATE" APK_SHA="$APK_SHA" APP_VERSION="$APP_VERSION" \
  BUILD_VARIANT="$BUILD_VARIANT" ENVIRONMENT="$ENVIRONMENT" SERIAL_HASH="$SERIAL_HASH" \
  OPERATOR="${UIX7_OP_OPERATOR:-}" OFF_CREF="$OFF_CREF" ON_CREF="$ON_CREF" \
  _py <<'PY'
import json,os
o=os.environ
json.dump({
  "run_id":o["RUN_ID"],
  "runtime_app_source_commit":o["ANCHOR"],
  "repository_commit":o["REPO"],
  "candidate_commit":(o["CANDIDATE"] or None),
  "apk_sha256":o["APK_SHA"],
  "app_version":o["APP_VERSION"],
  "build_variant":o["BUILD_VARIANT"],
  "environment":o["ENVIRONMENT"],
  "device_serial_hash":o["SERIAL_HASH"],
  "operator":o["OPERATOR"],
  "offline_client_reference":o["OFF_CREF"],
  "online_client_reference":o["ON_CREF"],
  "evidence_source":"physical",
  "scenarios":{}
}, open(o["SESSION"],"w"), indent=2)
print("session:",o["SESSION"])
PY
}

session_get(){ # key
  [ -f "$SESSION" ] || die "no open run; run 'preflight' first"
  SESSION="$SESSION" _py "$1" <<'PY'
import json,os,sys
d=json.load(open(os.environ["SESSION"])); print(d.get(sys.argv[1],"") if d.get(sys.argv[1]) is not None else "")
PY
}

row_status(){ # scenario_id -> PASS/FAIL/PENDING/absent
  SESSION="$SESSION" _py "$1" <<'PY'
import json,os,sys
d=json.load(open(os.environ["SESSION"])); r=d["scenarios"].get(sys.argv[1]); print(r["status"] if r else "absent")
PY
}

record_row(){ # sid result observation screenshot txn cref db method evref
  SESSION="$SESSION" _py "$@" <<'PY'
import json,os,sys,datetime
(sid,res,obs,shot,txn,cref,dbref,method,evref)=sys.argv[1:10]
path=os.environ["SESSION"]; d=json.load(open(path))
ts=os.environ.get("OP_EXECUTED_AT") or datetime.datetime.now().astimezone().date().isoformat()
d["scenarios"][sid]={
  "status":res,
  "evidence_source":"physical",
  "observation":obs,
  "screenshot_reference":shot,
  "transaction_reference":txn,
  "client_reference":cref,
  "db_reference":dbref,
  "run_id":d["run_id"],
  "runtime_app_source_commit":d["runtime_app_source_commit"],
  "apk_sha256":d["apk_sha256"],
  "app_version":d["app_version"],
  "build_variant":d["build_variant"],
  "environment":d["environment"],
  "device_serial_hash":d["device_serial_hash"],
  "executed_at":ts,
  "verification_method":(method or "physical-device operator-observed: adb -s <serial> + scoped DB query"),
  "evidence_reference":evref,
}
json.dump(d,open(path,"w"),indent=2)
PY
}

# --- subcommands -----------------------------------------------------------
cmd_preflight(){
  # Explicit, validated device serial — never the first adb device.
  SERIAL="${UIX7_OP_SERIAL:-}"
  [ -n "$SERIAL" ] || die "UIX7_OP_SERIAL is required (this runner never auto-selects a device)"

  # Physical evidence source only.
  local src; src="$(normalize_source "${UIX7_OP_EVIDENCE_SOURCE:-physical}")"
  [ "$src" = "physical" ] || die "this is the physical-device runner; evidence_source normalizes to '$src', expected 'physical'"

  # Runtime app-source anchor: a real commit, ancestor of current HEAD.
  git rev-parse --verify -q "${APP_SOURCE_COMMIT}^{commit}" >/dev/null || die "runtime app-source commit '$APP_SOURCE_COMMIT' is not a real git commit"
  REPO_COMMIT="$(git rev-parse HEAD)"
  git merge-base --is-ancestor "$APP_SOURCE_COMMIT" HEAD || die "runtime app-source '$APP_SOURCE_COMMIT' is not an ancestor of HEAD ($REPO_COMMIT) — cannot bind evidence"
  # HEAD is allowed to differ from the anchor (evidence/tooling/docs commits).

  # Optional final evidence-closure candidate must also be a real ancestor when supplied.
  if [ -n "$CANDIDATE" ]; then
    git rev-parse --verify -q "${CANDIDATE}^{commit}" >/dev/null || die "candidate_commit '$CANDIDATE' is not a real git commit"
  fi

  # APK exists + checksum matches the expected value.
  [ -f "$APK" ] || die "APK not found: $APK (build assemblePilot from the anchor first)"
  APK_SHA="$(sha256sum "$APK" | awk '{print $1}')"
  [ -n "${UIX7_OP_APK_SHA256:-}" ] || die "UIX7_OP_APK_SHA256 (expected APK sha256) is required for preflight"
  [ "$APK_SHA" = "$UIX7_OP_APK_SHA256" ] || die "APK sha256 mismatch: got $APK_SHA expected $UIX7_OP_APK_SHA256 (UIX7-R076)"

  # Device + package checks (skippable for tests / dry runs).
  if [ "$SKIP_ADB" != "1" ]; then
    command -v "$ADB" >/dev/null 2>&1 || die "adb not found ($ADB)"
    local state; state="$(adbx get-state 2>&1)"
    case "$state" in
      device) grn "device online: $SERIAL" ;;
      *unauthorized*) die "device $SERIAL is unauthorized (accept the RSA prompt on the device)" ;;
      *offline*)      die "device $SERIAL is offline" ;;
      *)              die "device $SERIAL not found / not ready (adb get-state: $state)" ;;
    esac
    local sdk; sdk="$(adbx shell getprop ro.build.version.sdk 2>/dev/null | tr -d '\r')"
    [ -n "$sdk" ] && [ "$sdk" -ge "$MIN_SDK" ] 2>/dev/null || die "device API level '$sdk' unsupported (< minSdk $MIN_SDK)"
    adbx shell pm path "$PKG" 2>/dev/null | grep -q "package:" || die "$PKG not installed on $SERIAL (adb -s $SERIAL install -r $APK)"
    grn "package installed: $PKG, device API $sdk"
  else
    echo "adb: skipped (UIX7_OP_SKIP_ADB=1)"
  fi

  # Persist only the SHA-256-derived serial hash (never the raw serial).
  SERIAL_HASH="$(printf '%s' "$SERIAL" | sha256sum | awk '{print substr($1,1,12)}')"

  # Deterministic run id + shared clientReferences (no Date/rand needed).
  RUN_ID="${UIX7_OP_RUN_ID:-run-${APP_SOURCE_COMMIT:0:12}-$(git rev-parse --short HEAD)}"
  OFF_CREF="${UIX7_OP_OFFLINE_CREF:-cref-off-${RUN_ID}}"
  ON_CREF="${UIX7_OP_ONLINE_CREF:-cref-on-${RUN_ID}}"

  session_init
  grn "preflight PASS — run_id=$RUN_ID"
  echo "runtime_app_source_commit=$APP_SOURCE_COMMIT  repository_commit=$REPO_COMMIT"
  echo "candidate_commit=${CANDIDATE:-<unset — set only at final closure>}"
  echo "apk_sha256=$APK_SHA  device_serial_hash=$SERIAL_HASH"
  echo "offline clientReference=$OFF_CREF  online clientReference=$ON_CREF"
}

cmd_record(){
  local sid="${1:-}"; [ -n "$sid" ] || die "usage: record <scenario_id>"
  [ -f "$SESSION" ] || die "no open run; run 'preflight' first"
  SERIAL="${UIX7_OP_SERIAL:-}"

  # Scenario id must exist in the manifest AND be recordable here.
  manifest_has "$sid" || die "unknown scenario id '$sid' (not present in $MANIFEST)"
  in_set "$sid" "$PROTECTED" && die "scenario '$sid' is protected (H01–H04/Q01) and must not be mutated by this runner"
  in_set "$sid" "$RECORDABLE" || die "scenario '$sid' is not recordable by this runner (R01–R06, R11–R20 only)"

  # Run-id integrity: an explicitly supplied run id must match the open run.
  if [ -n "${OP_RUN_ID:-}" ]; then
    local sr; sr="$(session_get run_id)"
    [ "$OP_RUN_ID" = "$sr" ] || die "run_id mismatch: OP_RUN_ID='$OP_RUN_ID' != open run '$sr' (UIX8BOPS-R025/R026)"
  fi

  # Prerequisite gate.
  local pre; pre="$(prereq_of "$sid")"
  if [ -n "$pre" ]; then
    local ps; ps="$(row_status "$pre")"
    [ "$ps" = "PASS" ] || die "dependency not met: '$pre' is '$ps' (must be PASS before '$sid') — UIX8BOPS-R028"
  fi

  echo "== Scenario: $sid =="
  echo "PASS requires a real, substantive PHYSICAL-DEVICE observation of the behaviour."

  # Screenshot: supplied, else auto-capture from the EXPLICIT serial (never the first device).
  local shot="${OP_SCREENSHOT:-}"
  if [ -z "$shot" ] && [ "$SKIP_ADB" != "1" ]; then
    shot="$EVID_DIR/screenshots/${sid}.png"; mkdir -p "$(dirname "$shot")"
    adbx exec-out screencap -p > "$shot" 2>/dev/null && grn "screenshot: $shot" || { red "screenshot capture failed"; shot=""; }
  fi

  local res="${OP_RESULT:-}" obs="${OP_OBSERVATION:-}" txn="${OP_TXN_REF:-}" cref="${OP_CLIENT_REF:-}"
  local dbref="${OP_DB_REF:-}" method="${OP_METHOD:-}" evref="${OP_EVIDENCE_REF:-}"
  if [ -z "$res" ] && [ -t 0 ]; then
    read -r -p "Result [PASS/FAIL/PENDING] (blank=PENDING): " res
    read -r -p "Observation (substantive, what you actually saw): " obs
    if is_txn_row "$sid"; then
      read -r -p "Transaction reference (sale#/backend id): " txn
      read -r -p "Client reference (shared for this chain): " cref
    fi
    is_db_row "$sid" && read -r -p "DB evidence reference (sanitized aggregates): " dbref
  fi
  res="$(printf '%s' "$res" | tr '[:lower:]' '[:upper:]' | tr -d '[:space:]')"
  [ -n "$res" ] || res="PENDING"

  # A leaked secret is never persisted — refuse outright.
  has_secret "$obs" && die "observation appears to contain a secret/credential — refusing to persist (UIX7-R063)"
  [ -n "$dbref" ] && has_secret "$dbref" && die "DB reference appears to contain a secret/credential — refusing to persist (UIX7-R063)"

  # A mismatched client reference for a txn row is a hard error, not a downgrade.
  if [ "$res" = "PASS" ] && is_txn_row "$sid" && [ -n "$cref" ]; then
    local want; want="$(expected_cref "$sid")"
    [ "$cref" = "$want" ] || die "client_reference mismatch for $sid: got '$cref' expected shared '$want' (UIX8BOPS-R027/R044)"
  fi

  # Fail-closed downgrades: any unmet requirement => PENDING, never a fabricated PASS.
  if [ "$res" = "PASS" ]; then
    is_substantive "$obs" "$sid" || { red "observation not substantive -> PENDING (UIX8BOPS-R031/R032)"; res="PENDING"; }
    { [ -n "$shot" ] && is_png "$shot"; } || { red "missing/empty/non-PNG screenshot -> PENDING (UIX8BOPS-R029/R033)"; res="PENDING"; }
    if is_txn_row "$sid"; then
      [ -n "$txn" ]  || { red "transaction row without transaction reference -> PENDING (UIX8BOPS-R034)"; res="PENDING"; }
      [ -n "$cref" ] || { red "transaction row without client reference -> PENDING (UIX8BOPS-R027)"; res="PENDING"; }
    fi
    if is_db_row "$sid"; then
      { [ -n "$dbref" ] && is_substantive "$dbref" "$sid"; } || { red "row requires a sanitized DB reference -> PENDING (UIX8BOPS-R035)"; res="PENDING"; }
    fi
  fi

  record_row "$sid" "$res" "$obs" "$shot" "$txn" "$cref" "$dbref" "$method" "$evref"
  [ "$res" = "PASS" ] && grn "recorded $sid = PASS" || echo "recorded $sid = $res (not PASS)"
}

cmd_status(){
  [ -f "$SESSION" ] || die "no open run"
  SESSION="$SESSION" _py <<'PY'
import json,os
d=json.load(open(os.environ["SESSION"]))
print("run_id:",d["run_id"])
print("runtime_app_source_commit:",d["runtime_app_source_commit"],"  repository_commit:",d["repository_commit"])
print("candidate_commit:",d.get("candidate_commit"))
print("offline cref:",d["offline_client_reference"],"  online cref:",d["online_client_reference"])
for k in sorted(d["scenarios"]):
    v=d["scenarios"][k]; print(f"  {v['status']:8} {k}  {v.get('observation','')[:52]}")
print("captured scenarios:",len(d["scenarios"]))
PY
}

cmd_finalize(){
  [ -f "$SESSION" ] || die "no open run"
  SESSION="$SESSION" MANIFEST="$MANIFEST" RECORDABLE="$RECORDABLE" _py <<'PY'
import json,os
sess=json.load(open(os.environ["SESSION"])); man=json.load(open(os.environ["MANIFEST"]))
recordable=set(os.environ["RECORDABLE"].split())
srows=sess["scenarios"]
anchor=sess["runtime_app_source_commit"]

# Copy ONLY genuine session rows into recordable manifest scenarios, writing ONLY
# UIX-7 schema fields. Fresh PASS rows bind commit_sha to the runtime app-source
# anchor (never to current HEAD). H01–H04/Q01 and un-recorded rows are untouched.
for s in man["scenarios"]:
    sid=s.get("scenario_id")
    if sid not in recordable: continue
    sr=srows.get(sid)
    if not sr: continue
    s["result"]=sr["status"]
    s["evidence_source"]="physical"
    s["commit_sha"]=anchor
    s["app_version"]=sr["app_version"]
    s["apk_sha256"]=sr["apk_sha256"]
    s["build_variant"]=sr["build_variant"]
    s["environment"]=sr["environment"]
    s["executed_at"]=sr["executed_at"]
    s["verification_method"]=sr["verification_method"]
    ev=sr.get("evidence_reference","").strip()
    if not ev:
        parts=[sr.get("observation","").strip()]
        if sr.get("screenshot_reference"): parts.append("screenshot="+os.path.basename(sr["screenshot_reference"]))
        if sr.get("transaction_reference"): parts.append("txn="+sr["transaction_reference"])
        if sr.get("db_reference"): parts.append("db="+sr["db_reference"])
        ev=" | ".join(p for p in parts if p)
    s["evidence_reference"]=ev

# The source anchor is bound to the runtime app source (NOT current HEAD).
man["app_source_unchanged_since"]=anchor
# candidate_commit is set ONLY when a final evidence-closure candidate was supplied.
cand=sess.get("candidate_commit")
if cand:
    man["candidate_commit"]=cand

# Decision is fail-closed: GO only when every recordable row is PASS AND a real
# closure candidate is bound; otherwise the honest terminal state is retained.
present=[s for s in man["scenarios"] if s.get("scenario_id") in recordable]
nonpass=[s["scenario_id"] for s in present if s.get("result")!="PASS"]
if not nonpass and man.get("candidate_commit"):
    man["decision"]="GO — all recordable UIX-7 rows physically observed PASS; candidate bound."
else:
    man["decision"]="NO-GO — GO DEFERRED: %d non-PASS recordable row(s); candidate=%s." % (
        len(nonpass), man.get("candidate_commit"))
json.dump(man,open(os.environ["MANIFEST"],"w"),indent=2); open(os.environ["MANIFEST"],"a").write("\n")
print("manifest decision:",man["decision"])
print("non-PASS recordable rows:",len(nonpass), nonpass)
PY
}

case "${1:-}" in
  preflight) cmd_preflight ;;
  record)    shift; cmd_record "$@" ;;
  status)    cmd_status ;;
  finalize)  cmd_finalize ;;
  *) echo "usage: $0 {preflight|record <scenario_id>|status|finalize}"; exit 2 ;;
esac
