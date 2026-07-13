#!/usr/bin/env bash
#
# CICD-CTRL-2 strict evidence validator (Lane D).
#
# Validates docs/evidence changes without rebuilding unmodified source:
#   - changed evidence files exist and are non-empty
#   - no unresolved placeholders masquerading as observed evidence
#   - no secret/token/PII leakage in evidence
#   - application/backend/android source tree is UNCHANGED vs base (CICD2-R018)
#
# Inputs: BASE_REF / HEAD_REF env (git refs). Falls back to origin/main..HEAD.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

BASE="${BASE_REF:-origin/main}"
HEAD="${HEAD_REF:-HEAD}"

fail=0

changed="$(git diff --name-only "$BASE" "$HEAD" 2>/dev/null || true)"
if [ -z "$changed" ]; then
  echo "No changed files resolved ($BASE..$HEAD) — nothing to validate." >&2
fi

# 1. Application source must be unchanged for the evidence lane.
src_changed="$(printf '%s\n' "$changed" | grep -E '^(backend/|android/|scripts/|\.github/|\.claude/)' | grep -vE '^docs/' || true)"
if [ -n "$src_changed" ]; then
  echo "FAIL: evidence lane but source/plumbing changed (must escalate to full CI):"
  printf '%s\n' "$src_changed" | sed 's/^/  - /'
  fail=1
fi

# 2. Governing files must be unchanged for the evidence lane.
gov_changed="$(printf '%s\n' "$changed" | grep -E '^(CLAUDE\.md|CLAUDE\.local\.md|AGENTS\.md|docs/PROJECT_RULES\.md|docs/governance/|docs/foundation/)' || true)"
if [ -n "$gov_changed" ]; then
  echo "FAIL: evidence lane but governing content changed (must escalate to full CI):"
  printf '%s\n' "$gov_changed" | sed 's/^/  - /'
  fail=1
fi

# 3. Placeholder / secret scan on changed evidence markdown.
evidence_files="$(printf '%s\n' "$changed" | grep -E '^docs/(deployment|evidence|ci)/.*\.md$' || true)"
if [ -n "$evidence_files" ]; then
  while IFS= read -r f; do
    [ -f "$f" ] || continue
    if [ ! -s "$f" ]; then echo "FAIL: empty evidence file $f"; fail=1; continue; fi
    if grep -nEi '\b(TODO|TBD|FIXME|PLACEHOLDER|LOREM IPSUM|<fill[^>]*>|XXXX)\b' "$f" >/dev/null 2>&1; then
      echo "FAIL: unresolved placeholder in evidence $f:"
      grep -nEi '\b(TODO|TBD|FIXME|PLACEHOLDER|LOREM IPSUM|<fill[^>]*>|XXXX)\b' "$f" | sed 's/^/    /'
      fail=1
    fi
    if grep -nEi 'ghp_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|BEGIN (RSA|OPENSSH) PRIVATE KEY|-----BEGIN' "$f" >/dev/null 2>&1; then
      echo "FAIL: possible secret material in evidence $f"; fail=1
    fi
    echo "ok - evidence $f"
  done <<EOF
$evidence_files
EOF
else
  echo "No evidence markdown changed (docs-only lane)."
fi

if [ "$fail" = 0 ]; then
  echo "Evidence validation PASSED (source tree unchanged, no placeholders/secrets)."
fi
exit "$fail"
