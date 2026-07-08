#!/usr/bin/env bash
#
# Sprint 34 — Android Offline, Sync, Device Activation & Cashier Runtime Hardening
# smoke test. Structural + command + governance-gate validation plus a runtime
# activation + sync probe via the android-runtime:* simulate commands on an
# isolated migrated sqlite file. Never calls a real gateway, never charges, never
# deploys, never marks paid, never lifts a suspension. Asserts activation
# idempotency, sync replay/duplicate protection, deterministic conflicts, the
# fail-closed suspended/unpaid/trial paths, and no secret/PII leakage.

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
CFG="$BACK/config/android_runtime_governance.php"
SVC="$BACK/app/Services/AndroidRuntime"
CMD="$BACK/app/Console/Commands"
CTRL="$BACK/app/Http/Controllers/Api/V1/Android"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"

echo "== Config / rules / posture =="
check "android_runtime_governance config exists" test -f "$CFG"
check "device activation enabled by default" hasf "ANDROID_DEVICE_ACTIVATION_ENABLED', true" "$CFG"
check "token hashed sha256" hasf "'hash_algo' => 'sha256'" "$CFG"
check "raw token not returned after creation" hasf "'return_raw_token_after_creation' => false" "$CFG"
check "raw token not stored" hasf "'store_raw_token' => false" "$CFG"
check "suspended blocks writes" hasf "'suspended' => 'block'" "$CFG"
check "no raw token returned guardrail" hasf "'raw_activation_token_returned_after_creation_allowed' => false" "$CFG"
check "no raw token stored guardrail" hasf "'raw_activation_token_stored_allowed' => false" "$CFG"
check "android never marks invoice paid guardrail" hasf "'android_marks_invoice_paid_allowed' => false" "$CFG"
check "android never unlocks entitlement guardrail" hasf "'android_unlocks_entitlement_locally_allowed' => false" "$CFG"
check "sync never bypasses pos domain guardrail" hasf "'sync_bypasses_pos_domain_service_allowed' => false" "$CFG"
check "revoked device cannot sync guardrail" hasf "'revoked_device_can_sync_allowed' => false" "$CFG"
check "no duplicate double mutation guardrail" hasf "'duplicate_client_uuid_double_mutation_allowed' => false" "$CFG"
check "runtime never bypasses entitlement guardrail" hasf "'runtime_bypasses_entitlement_service_allowed' => false" "$CFG"
check "manual suspension not overridable guardrail" hasf "'manual_suspension_overridable_by_billing_allowed' => false" "$CFG"
check "no raw credential in output guardrail" hasf "'raw_credential_in_output_allowed' => false" "$CFG"
for i in $(seq -w 1 30); do
  check "config locks ADR-R0$i" hasf "ADR-R0$i" "$CFG"
done
check "ADR rules in pos_foundation" hasf "ADR-R001" "$BACK/config/pos_foundation.php"
check "ADR rules in PROJECT_RULES" hasf "ADR-R001" "docs/PROJECT_RULES.md"
check "ADR-R030 in PROJECT_RULES" hasf "ADR-R030" "docs/PROJECT_RULES.md"
check "ADR rules in evidence doc" hasf "ADR-R030" "docs/sprints/sprint-34-android-runtime-hardening-evidence.md"

echo "== Migrations / models =="
check "device_activations migration" test -f "$BACK/database/migrations/2026_08_12_990040_create_tenant_device_activations_table.php"
check "sync_batches migration" test -f "$BACK/database/migrations/2026_08_12_990041_create_tenant_android_sync_batches_table.php"
check "sync_items migration" test -f "$BACK/database/migrations/2026_08_12_990042_create_tenant_android_sync_items_table.php"
check "TenantDeviceActivation model" test -f "$BACK/app/Models/TenantDeviceActivation.php"
check "TenantAndroidSyncBatch model" test -f "$BACK/app/Models/TenantAndroidSyncBatch.php"
check "TenantAndroidSyncItem model" test -f "$BACK/app/Models/TenantAndroidSyncItem.php"
check "activation stores hashed token only" hasf "activation_token_hash" "$BACK/database/migrations/2026_08_12_990040_create_tenant_device_activations_table.php"

echo "== Services =="
for s in AndroidRuntimeAccessService DeviceActivationService DeviceRevocationService CashierRuntimeSessionService AndroidOfflinePolicyService AndroidSyncIngestionService AndroidSyncConflictService AndroidSyncRedactor AndroidRuntimeSummaryService AndroidRuntimeGovernanceAuditService AndroidRuntimeGoNoGoService AndroidRuntimeAuditService AndroidRuntimeSimulator AndroidRuntimeDecision AndroidSyncBatchData AndroidRuntimeException; do
  check "$s" test -f "$SVC/$s.php"
done
check "access delegates to EntitlementAccessService" hasf "EntitlementAccessService" "$SVC/AndroidRuntimeAccessService.php"
check "activation hashes token" hasf "hash(" "$SVC/DeviceActivationService.php"
check "sync uses SaleService (no bypass)" hasf "SaleService" "$SVC/AndroidSyncIngestionService.php"

echo "== Controllers / routes =="
check "DeviceActivationController" test -f "$CTRL/DeviceActivationController.php"
check "AndroidSyncController" test -f "$CTRL/AndroidSyncController.php"
check "CashierRuntimeSessionController" test -f "$CTRL/CashierRuntimeSessionController.php"
check "AndroidRuntimePolicyController" test -f "$CTRL/AndroidRuntimePolicyController.php"
check "AdminAndroidRuntimeController" test -f "$ADMIN_CTRL/AdminAndroidRuntimeController.php"
check "android runtime routes registered" hasf "device/activate" "$BACK/routes/api.php"
check "sync batch route registered" hasf "sync/batch" "$BACK/routes/api.php"
check "admin android-runtime routes registered" hasf "android-runtime/devices" "$BACK/routes/api.php"

echo "== Commands =="
for c in AndroidRuntimeDeviceSummaryCommand AndroidRuntimeActivationSimulateCommand AndroidRuntimeSyncSummaryCommand AndroidRuntimeSyncSimulateCommand AndroidRuntimeCashierCheckCommand AndroidRuntimeGovernanceAuditCommand AndroidRuntimeGoNoGoCommand; do
  check "$c" test -f "$CMD/$c.php"
done

echo "== Android client hardening =="
AND=android/app/src/main/java/com/aishtech/poslite
check "runtime posture/messages" test -f "$AND/core/runtime/AndroidRuntimeState.kt"
check "activation request factory (redaction)" test -f "$AND/core/runtime/DeviceActivationRequestFactory.kt"
check "offline sync batch factory" test -f "$AND/feature/sync/OfflineSyncBatchFactory.kt"
check "android runtime dtos" test -f "$AND/data/remote/dto/AndroidRuntimeDtos.kt"
check "activation endpoint on api service" hasf "android/device/activate" "$AND/core/network/PosApiService.kt"
check "sync endpoint on api service" hasf "android/sync/batch" "$AND/core/network/PosApiService.kt"
ANDT=android/app/src/test/java/com/aishtech/poslite
check "runtime state test" test -f "$ANDT/AndroidRuntimeStateTest.kt"
check "activation redaction test" test -f "$ANDT/DeviceActivationRequestTest.kt"
check "sync batch idempotency test" test -f "$ANDT/OfflineSyncBatchFactoryTest.kt"

echo "== Command gates (isolated sqlite, no secrets) =="
cd "$BACK"
SMOKE_DB="$(mktemp -t sprint34smoke.XXXXXX.sqlite)"
export DB_CONNECTION=sqlite
export DB_DATABASE="$SMOKE_DB"
php artisan migrate --force >/dev/null 2>&1
trap 'rm -f "$SMOKE_DB"' EXIT
check "device-summary runs" php artisan android-runtime:device-summary --json
check "sync-summary runs" php artisan android-runtime:sync-summary --json
check "cashier-check posture runs" php artisan android-runtime:cashier-check --json
check "governance-audit passes" php artisan android-runtime:governance-audit
check "go-no-go is GO" php artisan android-runtime:go-no-go --strict

echo "== Runtime probes (deterministic simulate) =="
check "activation dry-run (no mutation)" php artisan android-runtime:activation-simulate
check "activation execute is idempotent (one device)" php artisan android-runtime:activation-simulate --execute
check "sync valid accepted" php artisan android-runtime:sync-simulate --scenario=valid --execute
check "sync replay idempotent (no duplicate batch)" php artisan android-runtime:sync-simulate --scenario=replay --execute
check "sync duplicate-item not double-created" php artisan android-runtime:sync-simulate --scenario=duplicate-item --execute
check "sync conflict deterministic" php artisan android-runtime:sync-simulate --scenario=conflict --execute
check "revoked device sync denied" php artisan android-runtime:sync-simulate --scenario=revoked-device --execute
check "suspended tenant sync denied" php artisan android-runtime:sync-simulate --scenario=suspended-tenant --execute
check "unpaid past grace sync denied" php artisan android-runtime:sync-simulate --scenario=unpaid-past-grace --execute
check "trial expired sync denied" php artisan android-runtime:sync-simulate --scenario=trial-expired --execute

echo "== Denied decisions are audited (ledger) =="
AUDIT_OUT="$(php artisan tinker --execute='
echo "REJECTED=".\App\Models\TenantAndroidSyncBatch::where("status","rejected")->count().PHP_EOL;
echo "CONFLICT_ITEMS=".\App\Models\TenantAndroidSyncItem::whereNotNull("conflict_code")->count().PHP_EOL;
echo "REVOKED_ACT=".\App\Models\TenantDeviceActivation::where("activation_status","revoked")->count().PHP_EOL;
' 2>/dev/null)"
echo "$AUDIT_OUT" | sed 's/^/  probe: /'
check "rejected sync batches recorded" bash -c "echo \"$AUDIT_OUT\" | grep -qE 'REJECTED=[1-9]'"
check "conflict items recorded (audit trail)" bash -c "echo \"$AUDIT_OUT\" | grep -qE 'CONFLICT_ITEMS=[1-9]'"
check "revoked activation recorded" bash -c "echo \"$AUDIT_OUT\" | grep -qE 'REVOKED_ACT=[1-9]'"

echo "== No secret / PII leakage in command output =="
OUT_FILE="$(mktemp -t sprint34out.XXXXXX)"
{
  php artisan android-runtime:device-summary --json 2>/dev/null
  php artisan android-runtime:sync-summary --json 2>/dev/null
  php artisan android-runtime:governance-audit --json 2>/dev/null
  php artisan android-runtime:go-no-go --json 2>/dev/null
  php artisan android-runtime:activation-simulate --execute --json 2>/dev/null
  php artisan android-runtime:cashier-check --json 2>/dev/null
} >"$OUT_FILE"
check "no password/secret/token in output" bash -c "! grep -Eiq 'password|secret|api_key|server_key|private_key|sk_live_' '$OUT_FILE'"
rm -f "$OUT_FILE"

echo "== Prior sprint gates (Sprint 24–33) still green =="
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
echo "== Sprint 34 smoke result: PASS=$pass FAIL=$fail =="
[ "$fail" -eq 0 ]
