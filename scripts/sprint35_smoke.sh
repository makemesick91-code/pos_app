#!/usr/bin/env bash
#
# Sprint 35 — Support Console, Tenant Operations & Incident Diagnostics smoke test.
# Structural + command + governance-gate validation plus a deterministic support
# probe (tenant health, timeline, incident create + redacted note, device
# revoke) on an isolated migrated sqlite file. Never calls a real gateway, never
# charges, never deploys, never marks an invoice paid, never unlocks entitlement,
# never lifts a suspension. Asserts platform-admin-only routing, no tenant/public
# support mutation route, revoked devices stay blocked, support actions are
# audited, impersonation is disabled, and no secret/PII leakage.

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
CFG="$BACK/config/support_operations_governance.php"
SVC="$BACK/app/Services/SupportOperations"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"

echo "== Config / rules / posture =="
check "support_operations_governance config exists" test -f "$CFG"
check "console enabled default" hasf "SUPPORT_CONSOLE_ENABLED" "$CFG"
check "read-only by default" hasf "'read_only_by_default' => true" "$CFG"
check "reason required for mutation" hasf "'reason_required_for_mutation' => true" "$CFG"
check "impersonation disabled by default" hasf "SUPPORT_IMPERSONATION_ENABLED', false" "$CFG"
check "reactivate disabled by default" hasf "SUPPORT_DEVICE_REACTIVATE_ENABLED', false" "$CFG"
check "no mark invoice paid guardrail" hasf "'support_marks_invoice_paid_allowed' => false" "$CFG"
check "no unlock entitlement guardrail" hasf "'support_unlocks_entitlement_allowed' => false" "$CFG"
check "no bypass settlement guardrail" hasf "'support_bypasses_payment_settlement_allowed' => false" "$CFG"
check "no lift suspension guardrail" hasf "'support_lifts_manual_suspension_allowed' => false" "$CFG"
check "no reactivate suspended guardrail" hasf "'support_reactivates_suspended_tenant_allowed' => false" "$CFG"
check "no mutate without service guardrail" hasf "'support_mutates_state_without_governed_service_allowed' => false" "$CFG"
check "no public/tenant mutation guardrail" hasf "'support_console_public_or_tenant_mutation_allowed' => false" "$CFG"
check "no impersonation without governance guardrail" hasf "'impersonation_enabled_without_governance_allowed' => false" "$CFG"
check "no raw credential guardrail" hasf "'impersonation_exposes_raw_credentials_allowed' => false" "$CFG"
check "no secret/PII leak guardrail" hasf "'support_output_leaks_secret_or_pii_allowed' => false" "$CFG"
for i in $(seq -w 1 30); do
  check "config locks SUP-R0$i" hasf "SUP-R0$i" "$CFG"
done
check "SUP rules in pos_foundation" hasf "SUP-R001" "$BACK/config/pos_foundation.php"
check "SUP rules in PROJECT_RULES" hasf "SUP-R001" "docs/PROJECT_RULES.md"
check "SUP-R030 in PROJECT_RULES" hasf "SUP-R030" "docs/PROJECT_RULES.md"
check "SUP rules in evidence doc" hasf "SUP-R030" "docs/sprints/sprint-35-support-console-tenant-operations-incident-diagnostics-evidence.md"

echo "== Migrations / models =="
check "incidents migration" test -f "$BACK/database/migrations/2026_09_01_996001_create_tenant_support_incidents_table.php"
check "incident notes migration" test -f "$BACK/database/migrations/2026_09_01_996002_create_tenant_support_incident_notes_table.php"
check "support actions migration" test -f "$BACK/database/migrations/2026_09_01_996003_create_tenant_support_actions_table.php"
check "support sessions migration" test -f "$BACK/database/migrations/2026_09_01_996004_create_tenant_support_sessions_table.php"
for m in TenantSupportIncident TenantSupportIncidentNote TenantSupportAction TenantSupportSession; do
  check "$m model" test -f "$BACK/app/Models/$m.php"
done

echo "== Services =="
for s in SupportRedactor SupportException SupportAuditService SupportTenantHealthService SupportDiagnosticTimelineService SupportBillingViewerService SupportPaymentViewerService SupportEntitlementViewerService SupportOnboardingViewerService SupportAndroidRuntimeViewerService SupportDeviceOperationsService SupportIncidentService SupportReadOnlyContextService SupportImpersonationService SupportGovernanceAuditService SupportGoNoGoService; do
  check "$s" test -f "$SVC/$s.php"
done
check "device ops uses Sprint 34 DeviceRevocationService" hasf "DeviceRevocationService" "$SVC/SupportDeviceOperationsService.php"
check "entitlement viewer reads decision ledger" hasf "TenantEntitlementDecision" "$SVC/SupportEntitlementViewerService.php"
check "sync viewer reads Sprint 34 batches" hasf "TenantAndroidSyncBatch" "$SVC/SupportAndroidRuntimeViewerService.php"
check "impersonation disabled service throws" hasf "impersonationDisabled" "$SVC/SupportImpersonationService.php"

echo "== Controllers / routes =="
check "AdminSupportConsoleController" test -f "$ADMIN_CTRL/AdminSupportConsoleController.php"
check "AdminSupportDeviceController" test -f "$ADMIN_CTRL/AdminSupportDeviceController.php"
check "AdminSupportIncidentController" test -f "$ADMIN_CTRL/AdminSupportIncidentController.php"
check "AdminSupportSessionController" test -f "$ADMIN_CTRL/AdminSupportSessionController.php"
check "support-ops routes registered" hasf "support-ops" "$BACK/routes/api.php"
check "device revoke route registered" hasf "devices/{activation}/revoke" "$BACK/routes/api.php"
check "read-only context route registered" hasf "read-only-context/start" "$BACK/routes/api.php"
check "no public support mutation route" bash -c "! grep -RynE 'support-ops' $BACK/routes/api.php | grep -viE 'middleware|prefix' | grep -iE 'Route::(post|patch|delete)' | grep -viE 'admin'"

echo "== Commands =="
for c in SupportOpsTenantHealthCommand SupportOpsTimelineCommand SupportOpsBillingStatusCommand SupportOpsPaymentStatusCommand SupportOpsEntitlementDenialsCommand SupportOpsSyncFailuresCommand SupportOpsIncidentSummaryCommand SupportOpsDeviceActionCommand SupportOpsGovernanceAuditCommand SupportOpsGoNoGoCommand; do
  check "$c" test -f "$CMD/$c.php"
done

echo "== Command gates (isolated sqlite, no secrets) =="
cd "$BACK"
SMOKE_DB="$(mktemp -t sprint35smoke.XXXXXX.sqlite)"
export DB_CONNECTION=sqlite
export DB_DATABASE="$SMOKE_DB"
php artisan migrate --force >/dev/null 2>&1
trap 'rm -f "$SMOKE_DB"' EXIT
check "tenant-health runs" php artisan support-ops:tenant-health --json
check "timeline runs" php artisan support-ops:timeline --json
check "billing-status runs" php artisan support-ops:billing-status --json
check "payment-status runs" php artisan support-ops:payment-status --json
check "entitlement-denials runs" php artisan support-ops:entitlement-denials --json
check "sync-failures runs" php artisan support-ops:sync-failures --json
check "incident-summary runs" php artisan support-ops:incident-summary --json
check "device-action dry-run runs" php artisan support-ops:device-action --json
check "governance-audit passes" php artisan support-ops:governance-audit
check "go-no-go is GO" php artisan support-ops:go-no-go --strict

echo "== Deterministic support probe (seed + revoke + audit) =="
PROBE_OUT="$(php artisan tinker --execute='
use App\Models\Tenant; use App\Models\User; use App\Models\Store;
use App\Services\AndroidRuntime\DeviceActivationService;
use App\Services\SupportOperations\SupportDeviceOperationsService;
use App\Services\SupportOperations\SupportIncidentService;
use App\Models\TenantSupportAction; use App\Models\TenantDeviceActivation;
$t = Tenant::factory()->create(["code" => "SUP-SMOKE"]);
$s = Store::factory()->create(["tenant_id" => $t->id]);
$owner = User::factory()->create(["tenant_id" => $t->id, "store_id" => $s->id, "role" => User::ROLE_TENANT_OWNER]);
$admin = User::factory()->platformAdmin()->create();
$inc = app(SupportIncidentService::class)->create($t, $admin, ["reason_code"=>"tenant_request","category"=>"device","severity"=>"low","title"=>"probe","summary"=>"reach owner@example.com"]);
app(SupportIncidentService::class)->addNote($inc, $admin, ["reason_code"=>"tenant_request","body"=>"note secret token abcdefabcdefabcdefabcdefabcdefabcdefabcdef"]);
$act = app(DeviceActivationService::class)->activate($t, "smoke-token-xyz", "smoke-fp", "smoke-dev", "Kasir", $owner);
app(SupportDeviceOperationsService::class)->revoke($act, $admin, "device_lost_or_stolen");
echo "REVOKED=".TenantDeviceActivation::where("id",$act->id)->where("activation_status","revoked")->count().PHP_EOL;
echo "ACTIONS=".TenantSupportAction::where("tenant_id",$t->id)->count().PHP_EOL;
echo "NOTE_HAS_EMAIL=".(App\Models\TenantSupportIncidentNote::where("tenant_id",$t->id)->get()->contains(fn($n)=>str_contains($n->body_safe,"owner@example.com"))?"1":"0").PHP_EOL;
echo "INVOICE_PAID=".App\Models\TenantBillingInvoice::where("tenant_id",$t->id)->where("collection_state","paid")->count().PHP_EOL;
' 2>/dev/null)"
echo "$PROBE_OUT" | sed 's/^/  probe: /'
check "revoked device stays revoked" bash -c "echo \"$PROBE_OUT\" | grep -qE 'REVOKED=1'"
check "support actions audited" bash -c "echo \"$PROBE_OUT\" | grep -qE 'ACTIONS=[3-9]'"
check "note body redacted (no PII email)" bash -c "echo \"$PROBE_OUT\" | grep -qE 'NOTE_HAS_EMAIL=0'"
check "no invoice marked paid by support" bash -c "echo \"$PROBE_OUT\" | grep -qE 'INVOICE_PAID=0'"

echo "== No secret / PII leakage in command output =="
OUT_FILE="$(mktemp -t sprint35out.XXXXXX)"
{
  php artisan support-ops:tenant-health --json 2>/dev/null
  php artisan support-ops:timeline --tenant=SUP-SMOKE --json 2>/dev/null
  php artisan support-ops:incident-summary --json 2>/dev/null
  php artisan support-ops:governance-audit --json 2>/dev/null
  php artisan support-ops:go-no-go --json 2>/dev/null
} >"$OUT_FILE"
check "no password/secret/token in output" bash -c "! grep -Eiq 'password|secret|api_key|server_key|private_key|sk_live_|owner@example.com' '$OUT_FILE'"
rm -f "$OUT_FILE"

echo "== Prior sprint gates (Sprint 24–34) still green =="
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
echo "== Sprint 35 smoke result: PASS=$pass FAIL=$fail =="
[ "$fail" -eq 0 ]
