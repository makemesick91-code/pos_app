#!/usr/bin/env bash
#
# CICD-CTRL-2 consolidated backend governance runner.
#
# Runs every per-sprint governance smoke script ONCE (deps installed once by the
# caller) instead of duplicating them across ~44 separate workflows. This
# PRESERVES all per-sprint runtime gates (CICD2-R011/R012) while removing the
# redundant re-provisioning. The full `php artisan test` suite runs separately
# in the backend-suite job.
#
# Fail-closed: any failing smoke script fails the job.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

fail=0
ran=0
for s in scripts/sprint*_smoke.sh; do
  [ -f "$s" ] || continue
  ran=$((ran + 1))
  echo "==================================================================="
  echo "=== $s"
  echo "==================================================================="
  if bash "$s"; then
    echo "PASS: $s"
  else
    echo "FAIL: $s"
    fail=1
  fi
done

echo "-------------------------------------------------------------------"
echo "Consolidated governance: ran=$ran failures=$([ "$fail" = 0 ] && echo 0 || echo '>=1')"
if [ "$ran" -eq 0 ]; then
  echo "ERROR: no smoke scripts found (fail-closed)" >&2
  exit 1
fi
exit "$fail"
