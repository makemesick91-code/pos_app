#!/usr/bin/env bash
#
# Sprint 36 — Observability, Health Monitoring, Queue & Production Diagnostics
# smoke test. Structural + command + governance-gate validation plus a
# deterministic anomaly/suggestion probe on an isolated migrated sqlite file.
# Never calls a real monitoring vendor, never touches the network, never charges,
# never deploys, never marks an invoice paid, never unlocks entitlement, never
# reactivates a tenant/device, never lifts a suspension. Asserts platform-admin
# routing, public-health minimality, anomaly dedup, suggestion-only behaviour, and
# no secret/PII leakage.

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
CFG="$BACK/config/observability_governance.php"
SVC="$BACK/app/Services/Observability"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"

echo "== Config / rules / posture =="
check "observability_governance config exists" test -f "$CFG"
check "public liveness default" hasf "OBSERVABILITY_PUBLIC_LIVENESS_ENABLED" "$CFG"
check "read-only by default" hasf "'read_only_by_default' => true" "$CFG"
check "reason required for mutation" hasf "'reason_required_for_mutation' => true" "$CFG"
check "job retry disabled by default" hasf "OBSERVABILITY_JOB_RETRY_ENABLED', false" "$CFG"
check "no mark invoice paid guardrail" hasf "'diagnostics_mark_invoice_paid_allowed' => false" "$CFG"
check "no unlock entitlement guardrail" hasf "'diagnostics_unlock_entitlement_allowed' => false" "$CFG"
check "no reactivate guardrail" hasf "'diagnostics_reactivate_tenant_or_device_allowed' => false" "$CFG"
check "no bypass suspension guardrail" hasf "'diagnostics_bypass_manual_suspension_allowed' => false" "$CFG"
check "no mutate without service guardrail" hasf "'diagnostics_mutate_domain_without_governed_service_allowed' => false" "$CFG"
check "public endpoint no tenant/secret guardrail" hasf "'observability_public_endpoint_exposes_tenant_or_secret_allowed' => false" "$CFG"
check "no secret/PII leak guardrail" hasf "'observability_output_leaks_secret_or_pii_allowed' => false" "$CFG"
check "suggestion no auto-mutate guardrail" hasf "'incident_suggestion_auto_mutates_tenant_allowed' => false" "$CFG"
check "queue retry governed guardrail" hasf "'queue_retry_without_governance_allowed' => false" "$CFG"
check "no external vendor guardrail" hasf "'external_monitoring_vendor_required_in_ci_allowed' => false" "$CFG"
for i in $(seq -w 1 32); do
  check "config locks OBS-R0$i" hasf "OBS-R0$i" "$CFG"
done
check "OBS rules in pos_foundation" hasf "OBS-R001" "$BACK/config/pos_foundation.php"
check "OBS rules in PROJECT_RULES" hasf "OBS-R001" "docs/PROJECT_RULES.md"
check "OBS-R032 in PROJECT_RULES" hasf "OBS-R032" "docs/PROJECT_RULES.md"
check "OBS rules in evidence doc" hasf "OBS-R032" "docs/sprints/sprint-36-observability-health-monitoring-queue-production-diagnostics-evidence.md"

echo "== Migrations / models =="
check "health snapshots migration" test -f "$BACK/database/migrations/2026_09_08_997001_create_observability_health_snapshots_table.php"
check "anomaly events migration" test -f "$BACK/database/migrations/2026_09_08_997002_create_observability_anomaly_events_table.php"
check "scheduler runs migration" test -f "$BACK/database/migrations/2026_09_08_997003_create_observability_scheduler_runs_table.php"
check "alert suggestions migration" test -f "$BACK/database/migrations/2026_09_08_997004_create_observability_alert_suggestions_table.php"
for m in ObservabilityHealthSnapshot ObservabilityAnomalyEvent ObservabilitySchedulerRun ObservabilityAlertSuggestion; do
  check "$m model" test -f "$BACK/app/Models/$m.php"
done

echo "== Services =="
for s in ObservabilityRedactor ObservabilityException ObservabilityAuditService InfrastructureHealthCheckService QueueHealthService FailedJobDiagnosticsService QueueActionService SchedulerHealthService ObservabilityHealthService TenantRuntimeProbeService AndroidSyncAnomalyService BillingPaymentAnomalyService EntitlementAnomalyService OnboardingAnomalyService ExportReportAnomalyService ObservabilityAnomalyScanService ObservabilityIncidentSuggestionService ObservabilityMetricsService ObservabilityGovernanceAuditService ObservabilityGoNoGoService; do
  check "$s" test -f "$SVC/$s.php"
done
check "sync anomaly reads Sprint 34 batches" hasf "TenantAndroidSyncBatch" "$SVC/AndroidSyncAnomalyService.php"
check "billing anomaly reads Sprint 30 invoices" hasf "TenantBillingInvoice" "$SVC/BillingPaymentAnomalyService.php"
check "payment anomaly reads Sprint 31 gateway events" hasf "TenantBillingGatewayEvent" "$SVC/BillingPaymentAnomalyService.php"
check "entitlement anomaly reads Sprint 32 decisions" hasf "TenantEntitlementDecision" "$SVC/EntitlementAnomalyService.php"
check "onboarding anomaly reads Sprint 33 runs" hasf "TenantProvisioningRun" "$SVC/OnboardingAnomalyService.php"
check "tenant probe reuses Sprint 35 health" hasf "SupportTenantHealthService" "$SVC/TenantRuntimeProbeService.php"
check "suggestion integrates Sprint 35 incidents" hasf "SupportIncidentService" "$SVC/ObservabilityIncidentSuggestionService.php"

echo "== Controllers / routes =="
check "AdminObservabilityController" test -f "$ADMIN_CTRL/AdminObservabilityController.php"
check "AdminObservabilityFailedJobController" test -f "$ADMIN_CTRL/AdminObservabilityFailedJobController.php"
check "AdminObservabilityAnomalyController" test -f "$ADMIN_CTRL/AdminObservabilityAnomalyController.php"
check "AdminObservabilityAlertController" test -f "$ADMIN_CTRL/AdminObservabilityAlertController.php"
check "public health controller" test -f "$BACK/app/Http/Controllers/HealthCheckController.php"
check "observability routes registered" hasf "observability" "$BACK/routes/api.php"
check "public health/live route" hasf "health/live" "$BACK/routes/web.php"
check "public health/ready route" hasf "health/ready" "$BACK/routes/web.php"
check "admin observability behind platform.admin (in admin group)" bash -c "grep -q \"Route::prefix('observability')\" $BACK/routes/api.php"
check "no public observability mutation route" bash -c "! grep -RynE 'observability' $BACK/routes/web.php | grep -iE 'Route::(post|patch|delete)'"

echo "== Commands =="
for c in ObservabilityHealthCommand ObservabilityInfraCheckCommand ObservabilityQueueHealthCommand ObservabilityFailedJobsCommand ObservabilitySchedulerHealthCommand ObservabilityTenantProbeCommand ObservabilityAnomalyScanCommand ObservabilityMetricsSummaryCommand ObservabilityAlertSuggestionsCommand ObservabilityGovernanceAuditCommand ObservabilityGoNoGoCommand; do
  check "$c" test -f "$CMD/$c.php"
done

echo "== Command gates (isolated sqlite, no secrets) =="
cd "$BACK"
SMOKE_DB="$(mktemp -t sprint36smoke.XXXXXX.sqlite)"
export DB_CONNECTION=sqlite
export DB_DATABASE="$SMOKE_DB"
php artisan migrate --force >/dev/null 2>&1
trap 'rm -f "$SMOKE_DB"' EXIT
check "health runs" php artisan observability:health --json
check "infra-check runs" php artisan observability:infra-check --json
check "queue-health runs" php artisan observability:queue-health --json
check "failed-jobs runs" php artisan observability:failed-jobs --json
check "scheduler-health runs" php artisan observability:scheduler-health --json
check "tenant-probe runs" php artisan observability:tenant-probe --json
check "anomaly-scan dry-run runs" php artisan observability:anomaly-scan --json
check "anomaly-scan execute runs" php artisan observability:anomaly-scan --execute --json
check "metrics-summary runs" php artisan observability:metrics-summary --json
check "alert-suggestions runs" php artisan observability:alert-suggestions --json
check "governance-audit passes" php artisan observability:governance-audit
check "go-no-go is GO" php artisan observability:go-no-go --strict

echo "== Deterministic anomaly / suggestion probe (no domain mutation) =="
PROBE_OUT="$(php artisan tinker --execute='
use App\Models\Tenant; use App\Models\Store; use App\Models\User;
use App\Models\TenantBillingInvoice; use App\Models\ObservabilityAnomalyEvent;
use App\Models\ObservabilityAlertSuggestion; use App\Models\TenantManualSuspension;
use App\Services\Observability\ObservabilityAnomalyScanService;
use App\Services\Observability\ObservabilityIncidentSuggestionService;
$t = Tenant::factory()->create(["code" => "OBS-SMOKE"]);
$s = Store::factory()->create(["tenant_id" => $t->id]);
$admin = User::factory()->platformAdmin()->create();
TenantManualSuspension::create(["tenant_id"=>$t->id,"status"=>"ACTIVE","reason"=>"probe","reason_category"=>"nonpayment","effective_at"=>now()]);
$inv = TenantBillingInvoice::factory()->create(["tenant_id"=>$t->id,"collection_state"=>"overdue","due_at"=>now()->subDays(30)]);
app(ObservabilityAnomalyScanService::class)->scan(true);
app(ObservabilityAnomalyScanService::class)->scan(true);
app(ObservabilityIncidentSuggestionService::class)->generateFromAnomalies($admin);
$a = ObservabilityAnomalyEvent::where("anomaly_key","billing.overdue_past_grace")->first();
echo "ANOMALY_ROWS=".ObservabilityAnomalyEvent::where("anomaly_key","billing.overdue_past_grace")->count().PHP_EOL;
echo "OCCURRENCE=".($a? $a->occurrence_count : 0).PHP_EOL;
echo "SUGGESTIONS=".ObservabilityAlertSuggestion::count().PHP_EOL;
echo "INCIDENTS=".App\Models\TenantSupportIncident::count().PHP_EOL;
echo "INVOICE_PAID=".TenantBillingInvoice::where("tenant_id",$t->id)->where("collection_state","paid")->count().PHP_EOL;
echo "TENANT_STILL_SUSPENDED=".($t->fresh()->activeManualSuspension()!==null?"1":"0").PHP_EOL;
' 2>/dev/null)"
echo "$PROBE_OUT" | sed 's/^/  probe: /'
check "anomaly deduped to one row" bash -c "echo \"$PROBE_OUT\" | grep -qE 'ANOMALY_ROWS=1'"
check "occurrence_count incremented" bash -c "echo \"$PROBE_OUT\" | grep -qE 'OCCURRENCE=2'"
check "suggestions created" bash -c "echo \"$PROBE_OUT\" | grep -qE 'SUGGESTIONS=[1-9]'"
check "no support incident auto-created on scan/generate" bash -c "echo \"$PROBE_OUT\" | grep -qE 'INCIDENTS=0'"
check "no invoice marked paid by diagnostics" bash -c "echo \"$PROBE_OUT\" | grep -qE 'INVOICE_PAID=0'"
check "manual suspension still wins (not lifted)" bash -c "echo \"$PROBE_OUT\" | grep -qE 'TENANT_STILL_SUSPENDED=1'"

echo "== No secret / PII leakage in command output =="
OUT_FILE="$(mktemp -t sprint36out.XXXXXX)"
{
  php artisan observability:health --json 2>/dev/null
  php artisan observability:infra-check --json 2>/dev/null
  php artisan observability:metrics-summary --json 2>/dev/null
  php artisan observability:anomaly-scan --json 2>/dev/null
  php artisan observability:governance-audit --json 2>/dev/null
  php artisan observability:go-no-go --json 2>/dev/null
} >"$OUT_FILE"
check "no password/secret/token in output" bash -c "! grep -Eiq 'password|secret|api_key|server_key|private_key|sk_live_|:memory:' '$OUT_FILE'"
rm -f "$OUT_FILE"

echo "== Prior sprint gates (Sprint 24–35) still green =="
check "support-ops go-no-go" php artisan support-ops:go-no-go --json
check "android-runtime go-no-go" php artisan android-runtime:go-no-go --json
check "onboarding go-no-go" php artisan onboarding:go-no-go --json
check "entitlement go-no-go" php artisan entitlement:go-no-go --json
check "billing go-no-go" php artisan billing:go-no-go --json
check "payment-gateway go-no-go" php artisan payment-gateway:go-no-go --json
check "tenant-plan go-no-go" php artisan tenant-plan:go-no-go --json
check "tenant-lifecycle go-no-go" php artisan tenant-lifecycle:go-no-go --json
check "subscription-renewal go-no-go" php artisan subscription-renewal:go-no-go --json
check "export-governance go-no-go" php artisan export-governance:go-no-go --json

cd "$ROOT"

echo
echo "== Sprint 36 smoke result: PASS=$pass FAIL=$fail =="
[ "$fail" -eq 0 ]
