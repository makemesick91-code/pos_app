#!/usr/bin/env bash

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

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

hasf() { grep -q "$1" "$2"; }

BACK=backend
CFG="$BACK/config/performance_governance.php"

echo "== Sprint 38 config / rules =="
check "performance governance config exists" test -f "$CFG"
check "ci smoke default" hasf "'default_profile' => 'ci_smoke'" "$CFG"
check "manual heavy present but not default" hasf "'manual_heavy'" "$CFG"
for i in $(seq -w 1 36); do
  check "config locks PERF-R0$i" hasf "PERF-R0$i" "$CFG"
  check "foundation locks PERF-R0$i" hasf "PERF-R0$i" "$BACK/config/pos_foundation.php"
  check "project rules lock PERF-R0$i" hasf "PERF-R0$i" "docs/PROJECT_RULES.md"
  check "architecture locks PERF-R0$i" hasf "PERF-R0$i" "docs/architecture/sprint-38-multi-tenant-performance-benchmark-load-gate-scale-readiness.md"
done

echo "== Isolated sqlite command smoke =="
cd "$BACK"
SMOKE_DB="$(mktemp -t sprint38smoke.XXXXXX.sqlite)"
export DB_CONNECTION=sqlite
export DB_DATABASE="$SMOKE_DB"
trap 'rm -f "$SMOKE_DB" "$OUT_FILE"' EXIT
php artisan migrate --force >/dev/null 2>&1

check "profile summary" php artisan performance:profile-summary --json
check "fixture dry-run" php artisan performance:fixture-build --profile=ci_smoke
check "fixture execute safe" php artisan performance:fixture-build --profile=ci_smoke --execute
check "performance run" php artisan performance:run --profile=ci_smoke --json
check "threshold check" php artisan performance:threshold-check --json
check "query review dry-run" php artisan performance:query-review --json
check "query review execute" php artisan performance:query-review --execute --json
check "queue pressure" php artisan performance:queue-pressure --profile=ci_smoke --json
check "performance smoke" php artisan performance:smoke --profile=ci_smoke --json
check "governance audit" php artisan performance:governance-audit --json
check "go/no-go" php artisan performance:go-no-go --json

COUNTS="$(php artisan tinker --execute='
echo "RUNS=".App\Models\PerformanceBenchmarkRun::count().PHP_EOL;
echo "FAILS=".App\Models\PerformanceBenchmarkRun::where("threshold_status","fail")->count().PHP_EOL;
echo "PAID=".App\Models\TenantBillingInvoice::where("status","paid")->count().PHP_EOL;
' 2>/dev/null)"
echo "$COUNTS" | sed 's/^/  probe: /'
check "benchmark run recorded" bash -c "echo \"$COUNTS\" | grep -q 'RUNS='"
check "no threshold failures" bash -c "echo \"$COUNTS\" | grep -q 'FAILS=0'"
check "no invoice marked paid" bash -c "echo \"$COUNTS\" | grep -q 'PAID=0'"

OUT_FILE="$(mktemp -t sprint38out.XXXXXX)"
php artisan performance:go-no-go --json > "$OUT_FILE" 2>/dev/null
check "no concrete secret leakage" bash -c "! grep -Eiq 'sk_live_|server_key_|private_key_|AKIA[0-9A-Z]' '$OUT_FILE'"
check "no raw pii leakage" bash -c "! grep -Eiq '@example\\.|0812|0899|address' '$OUT_FILE'"

cd "$ROOT"
echo "== Result =="
echo "PASS=$pass FAIL=$fail"
if [ "$fail" -ne 0 ]; then
  exit 1
fi
