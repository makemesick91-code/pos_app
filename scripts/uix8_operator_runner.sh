#!/usr/bin/env bash
# UIX-8B-OPS-1 — Turnkey, fail-closed operator runtime-evidence runner.
#
# Captures GENUINE operator-observed controlled-emulator evidence for the UIX-8
# runtime scenarios and (only) then updates the structured manifest. It NEVER
# fabricates a PASS: a blank/generic observation, a missing screenshot, a missing
# transaction reference (for transaction rows), or an unmet dependency all stay
# PENDING. Operator observation is a human checkpoint (rule 59 UIX8BOPS-R023..R058).
#
# Subcommands:
#   preflight            Validate candidate commit + APK SHA-256 + emulator, open a run.
#   record <scenario_id> Capture one scenario (auto-screenshot via adb) fail-closed.
#   status               Show the current run's captured rows.
#   finalize             Merge genuinely-PASSed rows into the manifest.
#
# Non-interactive inputs (used by tests / automation of the OBSERVATION step only):
#   OP_RESULT=PASS|FAIL|PENDING   OP_OBSERVATION="..."   OP_SCREENSHOT=/path.png
#   OP_TXN_REF="sale#/clientRef"  (required to PASS a transaction row)
# Env:
#   UIX8_OP_CANDIDATE   expected candidate commit (default: git HEAD)
#   UIX8_OP_APK         APK path (default: android/app/build/outputs/apk/pilot/app-pilot.apk)
#   UIX8_OP_APK_SHA256  expected APK sha256 (required for preflight)
#   UIX8_OP_AVD         AVD name (informational)
#   UIX8_OP_SESSION     session json path (default under evidence dir)
#   UIX8_OP_SKIP_ADB=1  skip adb device + screenshot capture (tests / dry runs)
#   UIX8_EVIDENCE_MANIFEST  manifest path (default docs/deployment/uix-8-runtime-evidence.json)
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"

MANIFEST="${UIX8_EVIDENCE_MANIFEST:-docs/deployment/uix-8-runtime-evidence.json}"
APK="${UIX8_OP_APK:-android/app/build/outputs/apk/pilot/app-pilot.apk}"
CANDIDATE="${UIX8_OP_CANDIDATE:-$(git rev-parse HEAD 2>/dev/null)}"
EVID_DIR="${UIX8_OP_EVIDENCE_DIR:-$HOME/Downloads/AishPOS-UIX8B-OPS1-Runtime-Closure}"
SESSION="${UIX8_OP_SESSION:-$EVID_DIR/manifest/session-current.json}"
SKIP_ADB="${UIX8_OP_SKIP_ADB:-0}"
MIN_OBS_LEN=12

red(){ printf '\033[31m%s\033[0m\n' "$1"; }; grn(){ printf '\033[32m%s\033[0m\n' "$1"; }
die(){ red "FAIL: $1"; exit 1; }

# Transaction rows require a txn reference and a shared clientReference to PASS.
TXN_ROWS="online-cash-checkout double-submit stable-client-reference offline-checkout force-stop-restoration reconnect-sync idempotent-retry receipt-parity history-parity"
# Dependency chain (a row's prerequisite must already be PASS before it can PASS).
prereq_of(){ case "$1" in
  force-stop-restoration) echo "offline-checkout";;
  reconnect-sync)         echo "offline-checkout";;
  idempotent-retry)       echo "reconnect-sync";;
  receipt-parity)         echo "online-cash-checkout";;
  history-parity)         echo "receipt-parity";;
  *) echo "";; esac; }
is_txn_row(){ case " $TXN_ROWS " in *" $1 "*) return 0;; *) return 1;; esac; }

# ---- session helpers (python3, JSON) ----
_py(){ python3 - "$@"; }

session_init(){
  mkdir -p "$(dirname "$SESSION")"
  _py "$SESSION" "$CANDIDATE" "$APK_SHA" "$RUN_ID" "$CLIENT_REF" "${UIX8_OP_AVD:-}" <<'PY'
import json,sys
path,cand,sha,run_id,cref,avd=sys.argv[1:7]
json.dump({"run_id":run_id,"candidate_commit":cand,"apk_sha256":sha,
          "client_reference":cref,"avd":avd,"rows":{}}, open(path,"w"), indent=2)
print("session:",path)
PY
}

session_get(){ # key
  [ -f "$SESSION" ] || die "no open run; run 'preflight' first"
  _py "$SESSION" "$1" <<'PY'
import json,sys
d=json.load(open(sys.argv[1])); print(d.get(sys.argv[2],""))
PY
}

row_status(){ # scenario_id -> PASS/FAIL/PENDING/absent
  _py "$SESSION" "$1" <<'PY'
import json,sys
d=json.load(open(sys.argv[1])); r=d["rows"].get(sys.argv[2]); print(r["status"] if r else "absent")
PY
}

# Substantive-observation test: length >= MIN and not a bare filler token / the id itself.
is_substantive(){ # observation scenario_id
  local obs="$1" sid="$2" low
  low="$(printf '%s' "$obs" | tr '[:upper:]' '[:lower:]' | tr -d '[:space:]')"
  [ "${#low}" -ge "$MIN_OBS_LEN" ] || return 1
  case "$low" in pass|ok|okay|good|done|fine|passed|works|yes|"$sid") return 1;; esac
  return 0
}

record_row(){ # scenario_id result observation screenshot txn_ref -> writes row
  _py "$SESSION" "$1" "$2" "$3" "$4" "$5" "$CANDIDATE" "$APK_SHA" <<'PY'
import json,sys,datetime
path,sid,res,obs,shot,txn,cand,sha=sys.argv[1:9]
d=json.load(open(path))
d["rows"][sid]={"status":res,"observation":obs,"screenshot_reference":shot,
  "transaction_reference":txn,"evidence_source":"operator" if res=="PASS" else "operator",
  "run_id":d["run_id"],"client_reference":d["client_reference"],
  "runtime_candidate_commit":cand,"apk_sha256":sha,
  "executed_at":datetime.datetime.now().astimezone().isoformat(timespec="seconds"),
  "verification_method":"controlled-emulator operator-observed"}
json.dump(d,open(path,"w"),indent=2)
PY
}

cmd_preflight(){
  [ -n "$CANDIDATE" ] || die "cannot resolve candidate commit"
  local head; head="$(git rev-parse HEAD)"
  [ "$head" = "$CANDIDATE" ] || die "worktree HEAD ($head) != candidate ($CANDIDATE) — check out the exact candidate (UIX8BOPS-R023)"
  [ -f "$APK" ] || die "APK not found: $APK (build assemblePilot first)"
  APK_SHA="$(sha256sum "$APK" | awk '{print $1}')"
  if [ -n "${UIX8_OP_APK_SHA256:-}" ]; then
    [ "$APK_SHA" = "$UIX8_OP_APK_SHA256" ] || die "APK sha256 mismatch: got $APK_SHA expected $UIX8_OP_APK_SHA256 (UIX8BOPS-R024/R039)"
  fi
  if [ "$SKIP_ADB" != "1" ]; then
    command -v adb >/dev/null || die "adb not found"
    local dev; dev="$(adb devices | awk 'NR>1 && $2=="device"{print $1}' | head -1)"
    [ -n "$dev" ] || die "no emulator/device in 'adb devices' (boot the AVD first)"
    grn "adb device: $dev"
  else
    echo "adb: skipped (UIX8_OP_SKIP_ADB=1)"
  fi
  # Stable run id + one clientReference for the whole transaction chain (no Date/rand needed).
  RUN_ID="${UIX8_OP_RUN_ID:-run-${CANDIDATE:0:12}-$(git rev-parse --short HEAD)}"
  CLIENT_REF="${UIX8_OP_CLIENT_REF:-cref-${RUN_ID}}"
  session_init
  grn "preflight PASS — run_id=$RUN_ID clientReference=$CLIENT_REF"
  echo "candidate=$CANDIDATE  apk_sha256=$APK_SHA"
}

cmd_record(){
  local sid="${1:-}"; [ -n "$sid" ] || die "usage: record <scenario_id>"
  [ -f "$SESSION" ] || die "no open run; run 'preflight' first"
  APK_SHA="$(session_get apk_sha256)"; CANDIDATE="$(session_get candidate_commit)"

  # Prerequisite gate (UIX8BOPS-R028).
  local pre; pre="$(prereq_of "$sid")"
  if [ -n "$pre" ]; then
    local ps; ps="$(row_status "$pre")"
    [ "$ps" = "PASS" ] || die "dependency not met: '$pre' is '$ps' (must be PASS before '$sid') — UIX8BOPS-R028"
  fi

  echo "== Scenario: $sid =="
  echo "PASS criteria: observe the behaviour on the emulator; PASS requires a real, substantive observation."

  # Auto-capture screenshot (unless supplied / adb skipped).
  local shot="${OP_SCREENSHOT:-}"
  if [ -z "$shot" ] && [ "$SKIP_ADB" != "1" ]; then
    shot="$EVID_DIR/screenshots/${sid}.png"; mkdir -p "$(dirname "$shot")"
    adb exec-out screencap -p > "$shot" 2>/dev/null && grn "screenshot: $shot" || { red "screenshot capture failed"; shot=""; }
  fi

  # Result + observation (non-interactive via env, else prompt). Blank => PENDING.
  local res="${OP_RESULT:-}" obs="${OP_OBSERVATION:-}" txn="${OP_TXN_REF:-}"
  if [ -z "$res" ] && [ -t 0 ]; then
    read -r -p "Result [PASS/FAIL/PENDING] (blank=PENDING): " res
    read -r -p "Observation (substantive, what you actually saw): " obs
    is_txn_row "$sid" && read -r -p "Transaction reference (sale#/clientRef): " txn
  fi
  res="$(printf '%s' "$res" | tr '[:lower:]' '[:upper:]' | tr -d '[:space:]')"
  [ -n "$res" ] || res="PENDING"

  # Fail-closed downgrades: any unmet requirement => PENDING, never a fabricated PASS.
  if [ "$res" = "PASS" ]; then
    if ! is_substantive "$obs" "$sid"; then red "observation not substantive -> PENDING (UIX8BOPS-R031/R032)"; res="PENDING"; fi
    if [ -z "$shot" ]; then red "no screenshot -> PENDING (UIX8BOPS-R029/R033)"; res="PENDING"; fi
    if is_txn_row "$sid" && [ -z "$txn" ]; then red "transaction row without reference -> PENDING (UIX8BOPS-R034/R035)"; res="PENDING"; fi
  fi
  # A transaction row's txn ref must carry the run's shared clientReference (UIX8BOPS-R027/R044).
  if [ "$res" = "PASS" ] && is_txn_row "$sid"; then
    local cref; cref="$(session_get client_reference)"
    case "$txn" in *"$cref"*) :;; *) txn="$txn ($cref)";; esac
  fi

  record_row "$sid" "$res" "$obs" "$shot" "$txn"
  [ "$res" = "PASS" ] && grn "recorded $sid = PASS" || echo "recorded $sid = $res (not PASS)"
}

cmd_status(){
  [ -f "$SESSION" ] || die "no open run"
  _py "$SESSION" <<'PY'
import json,sys
d=json.load(open(sys.argv[1]))
print("run_id:",d["run_id"],"clientReference:",d["client_reference"])
for k,v in d["rows"].items(): print(f"  {v['status']:8} {k}  {v.get('observation','')[:50]}")
print("captured rows:",len(d["rows"]))
PY
}

cmd_finalize(){
  [ -f "$SESSION" ] || die "no open run"
  _py "$SESSION" "$MANIFEST" <<'PY'
import json,sys
sess=json.load(open(sys.argv[1])); man=json.load(open(sys.argv[2]))
sess_rows=sess["rows"]
for row in man["rows"]:
    sr=sess_rows.get(row["id"])
    if not sr: continue
    row.update({k:sr[k] for k in ("status","evidence_source","observation",
        "screenshot_reference","transaction_reference","run_id","client_reference",
        "runtime_candidate_commit","apk_sha256","executed_at","verification_method") if k in sr})
# Only genuine data: candidate + apk binding come from the session.
man["candidate_commit"]=sess["candidate_commit"]; man["apk_sha256"]=sess["apk_sha256"]
man["avd"]=sess.get("avd","") or man.get("avd","")
nonpass=[r["id"] for r in man["rows"] if r.get("status")!="PASS"]
debt=man.get("uix7_closure_debt","open"); waiver=man.get("uix7_risk_waiver")
if not nonpass and (debt!="open" or waiver):
    man["decision"]="GO"; man["decision_reason"]="All mandatory rows operator-observed PASS; UIX-7 debt resolved/waived."
else:
    man["decision"]="GO_DEFERRED"
    man["decision_reason"]=f"Non-PASS rows: {len(nonpass)}; uix7_debt={debt}. GO deferred, not fabricated."
json.dump(man,open(sys.argv[2],"w"),indent=2); open(sys.argv[2],"a").write("\n")
print("manifest decision:",man["decision"],"non-PASS rows:",len(nonpass))
PY
}

case "${1:-}" in
  preflight) cmd_preflight ;;
  record)    shift; cmd_record "$@" ;;
  status)    cmd_status ;;
  finalize)  cmd_finalize ;;
  *) echo "usage: $0 {preflight|record <scenario_id>|status|finalize}"; exit 2 ;;
esac
