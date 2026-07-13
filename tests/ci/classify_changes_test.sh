#!/usr/bin/env bash
#
# Self-contained tests for scripts/ci/classify_changes.sh (CICD2-R004..R006).
# No bats dependency. Drives the classifier via CLASSIFY_FILES so it is
# deterministic and needs no git history.
#
# Usage: bash tests/ci/classify_changes_test.sh
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
CLS="$ROOT/scripts/ci/classify_changes.sh"
PASS=0; FAIL=0

# run <name> <files-newline-separated> -- <expected key=value>...
run() {
  local name="$1"; shift
  local files="$1"; shift
  # remaining args after "--" are expectations
  [ "$1" = "--" ] && shift
  local out
  out="$(CLASSIFY_FILES="$files" bash "$CLS" 2>/dev/null)"
  local ok=1 miss=""
  for exp in "$@"; do
    if ! printf '%s\n' "$out" | grep -qx "$exp"; then ok=0; miss="$miss [$exp]"; fi
  done
  if [ "$ok" = 1 ]; then
    PASS=$((PASS+1)); printf 'ok   - %s\n' "$name"
  else
    FAIL=$((FAIL+1)); printf 'FAIL - %s : missing%s\n' "$name" "$miss"
    printf '%s\n' "$out" | sed 's/^/       out: /'
  fi
}

# 1 Android-only source
run "android-only source" "android/app/src/main/java/Foo.kt" -- \
  full_ci_required=true android_changed=true backend_changed=false classification=full_ci
# 2 Backend-only source
run "backend-only source" "backend/app/Services/Billing/InvoiceService.php" -- \
  full_ci_required=true backend_changed=true android_changed=false
# 3 Docs-only
run "docs-only" "docs/uiux/notes.md" -- \
  full_ci_required=false docs_only=true evidence_only=false classification=docs_only
# 4 Evidence-only
run "evidence-only" "docs/deployment/cicd-ctrl-2-deployment-evidence.md" -- \
  full_ci_required=false evidence_only=true docs_only=true classification=evidence_only
# 5 Rules change (.claude)
run "rules change .claude" ".claude/rules/72-authoritative-ci-consolidation.md" -- \
  full_ci_required=true rules_changed=true
# 6 Workflow change
run "workflow change" ".github/workflows/ci-authoritative.yml" -- \
  full_ci_required=true workflow_changed=true
# 7 Script change
run "script change" "scripts/ci/classify_changes.sh" -- \
  full_ci_required=true deployment_changed=true
# 8 Dependency change
run "dependency lock change" "backend/composer.lock" -- \
  full_ci_required=true dependencies_changed=true
# 9 Migration/schema change
run "migration change" "backend/database/migrations/2026_01_01_000000_create_x.php" -- \
  full_ci_required=true database_changed=true backend_changed=true
# 10 Mixed docs + source
run "mixed docs + source" "$(printf 'docs/x.md\nbackend/app/Y.php')" -- \
  full_ci_required=true
# 11 Rename source -> docs (both paths present)
run "rename source->docs" "$(printf 'backend/app/Old.php\ndocs/new.md')" -- \
  full_ci_required=true backend_changed=true
# 12 Unknown new top-level path
run "unknown top-level path" "weird/thing.xyz" -- \
  full_ci_required=true classification=full_ci
# 13 Deleted security test (path still classified)
run "deleted security test" "backend/tests/Feature/PlatformAdminSecurityTest.php" -- \
  full_ci_required=true backend_changed=true
# 14 Evidence file + executable script
run "evidence + executable script" "$(printf 'docs/evidence/run.md\nscripts/deploy.sh')" -- \
  full_ci_required=true deployment_changed=true
# 15 CLAUDE/AGENTS update
run "AGENTS.md governing update" "AGENTS.md" -- \
  full_ci_required=true rules_changed=true
run "CLAUDE.md governing update" "CLAUDE.md" -- \
  full_ci_required=true rules_changed=true

# --- extra fail-closed / boundary cases ---
run "PROJECT_RULES is not docs" "docs/PROJECT_RULES.md" -- \
  full_ci_required=true rules_changed=true
run "governance doc is not lightweight" "docs/governance/ci-runtime-control.md" -- \
  full_ci_required=true rules_changed=true
run "foundation doc is not lightweight" "docs/foundation/uix-3-platform-admin-control-center.md" -- \
  full_ci_required=true rules_changed=true
run "root README is docs-light" "README.md" -- \
  full_ci_required=false docs_only=true
run "evidence screenshot png" "docs/evidence/pilot-login.png" -- \
  full_ci_required=false evidence_only=true
run "deployment .sh under docs is NOT light" "docs/deployment/apply.sh" -- \
  full_ci_required=true
run "security middleware change" "backend/app/Http/Middleware/EnsurePlatformAdmin.php" -- \
  full_ci_required=true security_sensitive_changed=true api_contract_changed=true
run "routes change is api-contract" "backend/routes/api.php" -- \
  full_ci_required=true api_contract_changed=true
run "gradle build config is dependency" "android/app/build.gradle.kts" -- \
  full_ci_required=true dependencies_changed=true
run "empty change set fails closed" "" -- \
  full_ci_required=true

echo "-----"
echo "PASS=$PASS FAIL=$FAIL"
[ "$FAIL" = 0 ]
