#!/usr/bin/env bash
#
# CICD-CTRL-2 source-equivalence verifier (CICD2-R007, R008).
#
# Proves that the current commit (a merge into main) contains exactly the source
# tree that was validated by the authoritative PR CI, so main-smoke can skip the
# full-suite re-run. Compares git TREE hashes (content), not commit SHAs, so it
# holds across merge, squash, and rebase merges.
#
#   main HEAD tree  ==  merged PR head tree   =>  equivalent (skip full re-run)
#   anything else / unprovable                =>  NOT equivalent (escalate to full)
#
# Fail-closed: any uncertainty prints equivalent=false. Emits key=value to stdout
# and, when set, to $GITHUB_OUTPUT.
set -uo pipefail

REPO="${GITHUB_REPOSITORY:-$(gh repo view --json nameWithOwner --jq .nameWithOwner 2>/dev/null || echo '')}"
HEAD_SHA="${1:-$(git rev-parse HEAD)}"
REASON=""
equivalent=false
pr_number=""

emit() {
  printf '%s=%s\n' "$1" "$2"
  [ -n "${GITHUB_OUTPUT:-}" ] && printf '%s=%s\n' "$1" "$2" >>"$GITHUB_OUTPUT"
}

head_tree="$(git rev-parse "${HEAD_SHA}^{tree}" 2>/dev/null || echo '')"
if [ -z "$head_tree" ]; then
  REASON="head-tree-unresolvable"
  emit equivalent false; emit reason "$REASON"; exit 0
fi

# 1. merge-commit message: "Merge pull request #N"
msg="$(git log -1 --format=%s%n%b "$HEAD_SHA" 2>/dev/null || echo '')"
pr_number="$(printf '%s' "$msg" | grep -oE 'Merge pull request #[0-9]+' | grep -oE '[0-9]+' | head -1)"

# 2. squash/rebase: ask the API which PR contains this commit
if [ -z "$pr_number" ] && [ -n "$REPO" ]; then
  pr_number="$(gh api "repos/$REPO/commits/$HEAD_SHA/pulls" --jq '.[0].number' 2>/dev/null || echo '')"
fi

if [ -z "$pr_number" ] || [ -z "$REPO" ]; then
  REASON="no-associated-pr(direct-push?)"
  emit equivalent false; emit reason "$REASON"; emit pr_number "${pr_number:-none}"; exit 0
fi

pr_head_sha="$(gh api "repos/$REPO/pulls/$pr_number" --jq '.head.sha' 2>/dev/null || echo '')"
if [ -z "$pr_head_sha" ]; then
  REASON="pr-head-sha-unresolvable"
  emit equivalent false; emit reason "$REASON"; emit pr_number "$pr_number"; exit 0
fi

# Tree SHA straight from the API — no need for the PR-head object locally.
pr_head_tree="$(gh api "repos/$REPO/git/commits/$pr_head_sha" --jq '.tree.sha' 2>/dev/null || echo '')"
if [ -z "$pr_head_tree" ]; then
  REASON="pr-head-tree-unresolvable"
  emit equivalent false; emit reason "$REASON"; emit pr_number "$pr_number"; exit 0
fi

if [ "$head_tree" = "$pr_head_tree" ]; then
  equivalent=true
  REASON="tree-match:$head_tree"
else
  equivalent=false
  REASON="tree-mismatch(main-tree=$head_tree pr-head-tree=$pr_head_tree)"
fi

emit equivalent "$equivalent"
emit reason "$REASON"
emit pr_number "$pr_number"
emit pr_head_sha "$pr_head_sha"
exit 0
