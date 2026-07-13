#!/usr/bin/env bash
#
# CICD-CTRL-2 CI architecture gate.
#
# Validates that the single-authoritative-full-CI + evidence-path architecture
# is intact and not silently weakened. Runs in the authoritative CI (foundation
# lane) and locally. Combines structural checks with BEHAVIORAL classifier
# assertions (the strongest guarantee that fail-closed classification holds).
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"
WF=".github/workflows"
CLS="scripts/ci/classify_changes.sh"
fail=0
ok()  { echo "ok   - $1"; }
bad() { echo "FAIL - $1"; fail=1; }

need_file() { [ -f "$1" ] && ok "exists: $1" || bad "missing: $1"; }

echo "== required workflows & scripts =="
for f in \
  "$WF/ci-authoritative.yml" "$WF/ci-main-smoke.yml" \
  "$WF/_backend-tests.yml" "$WF/_android-build.yml" "$WF/_foundation-gates.yml" \
  "$WF/_security-validation.yml" "$WF/_evidence-validation.yml" \
  "$CLS" "scripts/ci/run_backend_governance.sh" "scripts/ci/verify_source_equivalence.sh" \
  "scripts/ci/validate_evidence.sh" "tests/ci/classify_changes_test.sh" \
  ".claude/rules/72-authoritative-ci-consolidation.md"; do
  need_file "$f"
done

echo "== authoritative workflow shape =="
A="$WF/ci-authoritative.yml"
if [ -f "$A" ]; then
  grep -q 'name: AISH POS Authoritative PR CI' "$A" && ok "authoritative name identifiable" || bad "authoritative name missing"
  grep -qE '^\s*pull_request:' "$A" && ok "authoritative triggers on pull_request" || bad "authoritative not on pull_request"
  grep -qE '^concurrency:' "$A" && ok "authoritative has concurrency" || bad "authoritative missing concurrency"
  grep -qE 'cancel-in-progress:\s*true' "$A" && ok "concurrency cancels stale runs" || bad "concurrency not cancel-in-progress"
  grep -qE '^permissions:' "$A" && grep -qE 'contents:\s*read' "$A" && ok "authoritative least-privilege permissions" || bad "authoritative permissions not least-privilege"
  grep -q 'authoritative-summary' "$A" && ok "summary gate present" || bad "summary gate missing"
  # summary depends on all mandatory jobs
  if grep -qE 'needs:\s*\[classify, backend, android, foundation, security, evidence\]' "$A"; then
    ok "summary depends on all mandatory jobs"
  else
    bad "summary does not depend on all mandatory jobs"
  fi
fi

echo "== main smoke shape =="
M="$WF/ci-main-smoke.yml"
if [ -f "$M" ]; then
  grep -qE 'branches:\s*\[main\]' "$M" && ok "main-smoke on push:main" || bad "main-smoke not scoped to main"
  grep -q 'verify_source_equivalence.sh' "$M" && ok "main-smoke proves source equivalence" || bad "main-smoke missing equivalence check"
  grep -q 'escalate-backend' "$M" && grep -q 'escalate-android' "$M" && ok "main-smoke escalates to full when unproven" || bad "main-smoke missing escalation"
fi

echo "== no gate weakening in new workflows =="
NEW_WF=$(ls "$WF"/ci-authoritative.yml "$WF"/ci-main-smoke.yml "$WF"/_*.yml 2>/dev/null)
if grep -RnE 'continue-on-error:\s*true' $NEW_WF >/dev/null 2>&1; then
  bad "continue-on-error:true found in a new workflow (CICD2-R013)"; grep -RnE 'continue-on-error:\s*true' $NEW_WF
else ok "no continue-on-error:true in new workflows"; fi
if grep -RnE 'permissions:\s*write-all|contents:\s*write' $NEW_WF >/dev/null 2>&1; then
  bad "over-broad permissions in a new workflow"; else ok "no over-broad permissions in new workflows"; fi
if grep -RnE 'pull_request_target' "$WF"/*.yml >/dev/null 2>&1; then
  bad "pull_request_target present (CICD2-R017)"; else ok "no pull_request_target anywhere"; fi

echo "== legacy workflows neutralized (manual-only) =="
legacy_bad=0
for f in "$WF"/sprint*-ci.yml "$WF"/uix*-ci.yml; do
  [ -f "$f" ] || continue
  # must be workflow_dispatch-only: no pull_request:, no auto push:
  if grep -qE '^\s*pull_request:' "$f"; then echo "  still on pull_request: $f"; legacy_bad=1; fi
  if grep -qE '^\s*push:' "$f"; then echo "  still on push: $f"; legacy_bad=1; fi
  if ! grep -qE 'workflow_dispatch:' "$f"; then echo "  not workflow_dispatch: $f"; legacy_bad=1; fi
done
[ "$legacy_bad" = 0 ] && ok "all legacy sprint*/uix* workflows are manual-only" || bad "some legacy workflows still auto-trigger"

echo "== rules present (CICD2-R001..R024 + rule 70 retained) =="
[ -f ".claude/rules/70-ci-runtime-control.md" ] && ok "rule 70 (CICD-CTRL-1) retained" || bad "rule 70 missing"
missing_rule=""
for i in $(seq -w 1 24); do
  grep -q "CICD2-R0$i" ".claude/rules/72-authoritative-ci-consolidation.md" || missing_rule="$missing_rule R0$i"
done
[ -z "$missing_rule" ] && ok "CICD2-R001..R024 all present in rule 72" || bad "missing CICD2 rules:$missing_rule"

echo "== BEHAVIORAL: classifier is fail-closed =="
beh() { # <desc> <files> <expected-substring>
  local desc="$1" files="$2" exp="$3" out
  out="$(CLASSIFY_FILES="$files" bash "$CLS" 2>/dev/null)"
  if printf '%s\n' "$out" | grep -qx "$exp"; then ok "classifier: $desc"; else bad "classifier: $desc (want $exp)"; fi
}
beh "rules => full"        ".claude/rules/72-authoritative-ci-consolidation.md" "full_ci_required=true"
beh "workflow => full"     ".github/workflows/ci-authoritative.yml"             "full_ci_required=true"
beh "script => full"       "scripts/ci/classify_changes.sh"                     "full_ci_required=true"
beh "backend => full"      "backend/app/Services/Foo.php"                       "full_ci_required=true"
beh "android => full"      "android/app/src/Foo.kt"                             "full_ci_required=true"
beh "unknown path => full" "weird/thing.xyz"                                    "full_ci_required=true"
beh "migration => full"    "backend/database/migrations/x.php"                  "full_ci_required=true"
beh "docs => lightweight"  "docs/uiux/notes.md"                                 "full_ci_required=false"
beh "evidence => lightweight" "docs/deployment/x-evidence.md"                   "evidence_only=true"

echo "-----"
if [ "$fail" = 0 ]; then echo "CICD-CTRL-2 architecture gate: PASS"; else echo "CICD-CTRL-2 architecture gate: FAIL"; fi
exit "$fail"
