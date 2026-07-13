#!/usr/bin/env bash
#
# CICD-CTRL-2 change classifier (CICD2-R004, R005, R006).
#
# Repository-owned, FAIL-CLOSED classification of a change set. GitHub path
# filters are an optimization only; THIS script is the authoritative decision
# on whether a change may take the lightweight docs/evidence lane or must run
# the full authoritative CI.
#
# Contract:
#   - Inputs: BASE and HEAD refs as $1 $2, or env BASE_SHA / HEAD_SHA.
#     For deterministic testing, CLASSIFY_FILES (newline-separated paths)
#     bypasses git entirely.
#   - Output: machine-readable `key=value` lines on stdout; if $GITHUB_OUTPUT
#     is set, the same keys are appended there for job `outputs`.
#   - Fail-closed: an unresolved diff, an empty change set, an unknown path, or
#     any non-lightweight extension forces `full_ci_required=true`. The lightweight
#     lane is granted ONLY when every changed file matches the strict allowlist.
#
# Exit status is always 0 (the decision is in the output, not the exit code) so
# that a workflow step can read the outputs; pass --strict to exit 1 when the
# change set could not be resolved (used by local self-tests).

set -uo pipefail

STRICT_EXIT=0
[ "${1:-}" = "--strict" ] && { STRICT_EXIT=1; shift; }

BASE_REF="${1:-${BASE_SHA:-}}"
HEAD_REF="${2:-${HEAD_SHA:-}}"

# --- collect changed files (old AND new path for renames; includes deletes) ---
RESOLVE_ERROR=""
FILES=""
if [ -n "${CLASSIFY_FILES:-}" ]; then
  FILES="$CLASSIFY_FILES"
else
  if [ -z "$BASE_REF" ] || [ -z "$HEAD_REF" ]; then
    RESOLVE_ERROR="missing-base-or-head-ref"
  elif ! git rev-parse --verify --quiet "$BASE_REF^{commit}" >/dev/null 2>&1; then
    RESOLVE_ERROR="base-ref-unresolvable (shallow checkout?)"
  elif ! git rev-parse --verify --quiet "$HEAD_REF^{commit}" >/dev/null 2>&1; then
    RESOLVE_ERROR="head-ref-unresolvable"
  else
    # --name-status -M prints: <STATUS>\t<path>[\t<newpath>] — capture every path column.
    if ! DIFF_RAW="$(git diff --name-status -M "$BASE_REF" "$HEAD_REF" 2>/dev/null)"; then
      RESOLVE_ERROR="git-diff-failed"
    else
      FILES="$(printf '%s\n' "$DIFF_RAW" | awk -F'\t' 'NF>1{for(i=2;i<=NF;i++) print $i}')"
    fi
  fi
fi

# normalize
FILES="$(printf '%s\n' "$FILES" | sed '/^[[:space:]]*$/d')"
COUNT="$(printf '%s\n' "$FILES" | sed '/^$/d' | wc -l | tr -d ' ')"

# --- flags ---
docs_only=false
evidence_only=false
android_changed=false
backend_changed=false
database_changed=false
dependencies_changed=false
api_contract_changed=false
security_sensitive_changed=false
rules_changed=false
workflow_changed=false
deployment_changed=false
full_ci_required=false
REASON=""

FORCE_FULL=false
if [ -n "$RESOLVE_ERROR" ]; then
  FORCE_FULL=true
  REASON="unresolved-diff:$RESOLVE_ERROR"
elif [ "$COUNT" = "0" ]; then
  FORCE_FULL=true
  REASON="empty-change-set"
fi

# Lightweight-eligible extensions (non-executable content only).
is_light_ext() {
  case "${1##*.}" in
    md|txt|json|csv|png|jpg|jpeg|gif|svg|webp) return 0 ;;
    *) return 1 ;;
  esac
}

# Does a single path qualify for the lightweight docs/evidence lane?
#   evidence: docs/deployment/**, docs/evidence/**
#   docs:     docs/** and root *.md, EXCLUDING governing content
# Governing content (rules/foundation/governance/PROJECT_RULES/CLAUDE/AGENTS)
# is NEVER lightweight (CICD2-R005).
classify_one() {
  local f="$1" all=0
  # governing / rules
  case "$f" in
    .claude/*|CLAUDE.md|CLAUDE.local.md|AGENTS.md|docs/PROJECT_RULES.md|docs/governance/*|docs/foundation/*)
      rules_changed=true; all=1 ;;
  esac
  # workflow / CI plumbing
  case "$f" in
    .github/*) workflow_changed=true; all=1 ;;
  esac
  # deployment / executable scripts / container / runtime config
  case "$f" in
    scripts/*|deploy/*|Dockerfile|*/Dockerfile|docker-compose*.yml|*.Dockerfile|nginx/*|*.nginx|*.conf)
      deployment_changed=true; all=1 ;;
  esac
  # dependencies / lockfiles / build config
  case "$f" in
    backend/composer.json|backend/composer.lock|*/composer.json|*/composer.lock|\
    package.json|package-lock.json|yarn.lock|pnpm-lock.yaml|*/package.json|*/package-lock.json|\
    android/*.gradle|android/*.gradle.kts|android/**/*.gradle|android/**/*.gradle.kts|\
    android/gradle/*|android/gradle.properties|android/settings.gradle*|*/build.gradle|*/build.gradle.kts)
      dependencies_changed=true; all=1 ;;
  esac
  # database / migrations / schema
  case "$f" in
    backend/database/*|*/migrations/*|*_migration.php) database_changed=true; backend_changed=true; all=1 ;;
  esac
  # api contract (routes / http layer)
  case "$f" in
    backend/routes/*|backend/app/Http/*) api_contract_changed=true; backend_changed=true; all=1 ;;
  esac
  # security-sensitive
  case "$f" in
    .env|.env.*|*/.env|*/.env.*|\
    backend/app/Http/Middleware/*|backend/config/auth.php|backend/config/session.php|\
    backend/config/sanctum.php|backend/app/Policies/*|*Auth*.php|*Security*.php|*Middleware*.php)
      security_sensitive_changed=true; backend_changed=true; all=1 ;;
  esac
  # backend source (catch-all backend)
  case "$f" in
    backend/*) backend_changed=true; all=1 ;;
  esac
  # android source
  case "$f" in
    android/*) android_changed=true; all=1 ;;
  esac

  if [ "$all" = "1" ]; then
    # any source/rules/workflow/dep/db/deploy/security path => full CI
    return 2
  fi

  # Not a source path — is it lightweight docs/evidence?
  if ! is_light_ext "$f"; then
    return 3   # unknown/non-lightweight extension => fail closed
  fi
  case "$f" in
    docs/deployment/*|docs/evidence/*) return 0 ;;     # evidence-eligible
    docs/ci/*.md) return 1 ;;                           # docs (ci docs)
    docs/PROJECT_RULES.md) return 3 ;;                  # (already caught above)
    docs/governance/*|docs/foundation/*) return 3 ;;    # (already caught above)
    docs/*) return 1 ;;                                 # docs-eligible
    *.md)                                               # root-level docs
      case "$f" in */*) return 3 ;; *) return 1 ;; esac ;;
    *) return 3 ;;                                       # unknown top-level path => fail closed
  esac
}

saw_evidence=false
saw_docs=false
saw_nonlight=false
if [ "$FORCE_FULL" = false ]; then
  while IFS= read -r f; do
    [ -z "$f" ] && continue
    classify_one "$f"; rc=$?
    case $rc in
      0) saw_evidence=true ;;
      1) saw_docs=true ;;
      2) saw_nonlight=true ;;   # source/rules/etc
      3) saw_nonlight=true; [ -z "$REASON" ] && REASON="non-lightweight-or-unknown-path:$f" ;;
    esac
  done <<EOF
$FILES
EOF
fi

if [ "$FORCE_FULL" = true ] || [ "$saw_nonlight" = true ]; then
  full_ci_required=true
  [ -z "$REASON" ] && REASON="source-or-governing-change"
else
  # every file was lightweight docs/evidence
  if [ "$saw_docs" = false ] && [ "$saw_evidence" = true ]; then
    evidence_only=true; docs_only=true; REASON="evidence-only-allowlist"
  elif [ "$saw_docs" = true ] || [ "$saw_evidence" = true ]; then
    docs_only=true; REASON="docs-allowlist"
  else
    # nothing recognized but not forced (shouldn't happen) -> fail closed
    full_ci_required=true; REASON="unrecognized-empty"
  fi
fi

CLASSIFICATION="full_ci"
[ "$docs_only" = true ] && CLASSIFICATION="docs_only"
[ "$evidence_only" = true ] && CLASSIFICATION="evidence_only"

emit() {
  printf '%s=%s\n' "$1" "$2"
  [ -n "${GITHUB_OUTPUT:-}" ] && printf '%s=%s\n' "$1" "$2" >>"$GITHUB_OUTPUT"
}

emit docs_only "$docs_only"
emit evidence_only "$evidence_only"
emit android_changed "$android_changed"
emit backend_changed "$backend_changed"
emit database_changed "$database_changed"
emit dependencies_changed "$dependencies_changed"
emit api_contract_changed "$api_contract_changed"
emit security_sensitive_changed "$security_sensitive_changed"
emit rules_changed "$rules_changed"
emit workflow_changed "$workflow_changed"
emit deployment_changed "$deployment_changed"
emit full_ci_required "$full_ci_required"
emit classification "$CLASSIFICATION"
emit changed_files_count "$COUNT"
emit reason "$REASON"

# Human summary to stderr (does not pollute GITHUB_OUTPUT).
{
  echo "--- classify_changes: $CLASSIFICATION (files=$COUNT, reason=$REASON) ---"
} >&2

if [ "$STRICT_EXIT" = 1 ] && [ -n "$RESOLVE_ERROR" ]; then
  exit 1
fi
exit 0
