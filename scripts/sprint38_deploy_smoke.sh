#!/usr/bin/env bash

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT/backend"

pass=0
fail=0

check() {
  local desc="$1"; shift
  if "$@" >/dev/null 2>&1; then
    echo "  ok   - $desc"
    pass=$((pass + 1))
  else
    echo "  FAIL - $desc"
    fail=$((fail + 1))
  fi
}

echo "== Sprint 38 pilot/VPS deploy smoke =="
check "git commit readable" git rev-parse HEAD
check "migrations status readable" php artisan migrate:status
check "performance deploy check records pending unless confirmed" php artisan performance:deploy-check --environment=pilot_vps --git-commit="$(git rev-parse --short=12 HEAD)"
check "performance smoke pilot profile" php artisan performance:smoke --profile=pilot_vps --json
check "observability health" php artisan observability:health --json
check "no failed performance threshold" php artisan performance:threshold-check --json

echo "== Result =="
echo "PASS=$pass FAIL=$fail"
if [ "$fail" -ne 0 ]; then
  exit 1
fi
