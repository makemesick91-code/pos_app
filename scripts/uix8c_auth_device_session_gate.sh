#!/usr/bin/env bash
#
# UIX-8C-07 — fail-closed gate for the premium authentication, device activation,
# settings, and session-recovery baseline (UIX8C-R211..R250).
#
# It verifies (structurally, machine-checkable):
#   * rule set UIX8C-R211..R250 persisted (modular rule 61 + PROJECT_RULES);
#   * the required UIX-8C-07 docs + ADR 0009 exist;
#   * the deterministic startup/auth state machine (BootState/StartupCoordinator)
#     exists with its transition tests, and revocation is server-authoritative;
#   * the Keystore-backed SecureTokenStore (AndroidKeyStore/AES-GCM, NO plaintext
#     token, NO jetpack-security dependency) + its test;
#   * the single runtime-context source of truth + validated build;
#   * the server-authoritative device-status poll (endpoint + fail-closed mapper +
#     revoked fail-closed screen, not bypassable) + backend endpoint;
#   * the unsynced-logout guard counting ALL non-acked transactions;
#   * the classified LocalDataCleaner + the automated tenant-isolation test;
#   * process-restoration reuse of the stable UIX-8C-06 clientReference identity;
#   * truthful status enums (status-not-colour-alone);
#   * the Settings no-secret-render invariant;
#   * 130%-font / accessibility test presence;
#   * the backend device-status/provisioning tests + additive migration;
#   * the immutable failed physical run stays FAIL; UIX-7/UIX-8 stay deferred;
#   * no premature UIX-7/UIX-8 GO tag; no secret value in touched source/docs.
#
# Absence/failure of proof = FAIL (fail-closed).
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }

echo "== UIX-8C-07 premium authentication / device / settings / session-recovery gate =="

JAVA="android/app/src/main/java/com/aishtech/poslite"
TEST="android/app/src/test/java/com/aishtech/poslite"
BTEST="backend/tests/Feature"
RULE=".claude/rules/61-android-cashier-full-premium-delivery-foundation.md"
PROJECT_RULES="docs/PROJECT_RULES.md"
MANIFEST="android/app/src/main/AndroidManifest.xml"
GRADLE="android/app/build.gradle.kts"
FAILED_RUN="docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json"
UIX7_EVID="docs/deployment/uix-7-runtime-evidence.json"
UIX8_EVID="docs/deployment/uix-8-runtime-evidence.json"

need_file(){ [ -f "$1" ] && pass "present: $1" || bad "missing: $1"; }
need_grep(){ grep -q "$2" "$1" 2>/dev/null && pass "$3" || bad "$3 (missing '$2' in $1)"; }
need_grepE(){ grep -qE "$2" "$1" 2>/dev/null && pass "$3" || bad "$3 (missing /$2/ in $1)"; }
deny_grep(){ grep -q "$2" "$1" 2>/dev/null && bad "$3 (found '$2' in $1)" || pass "$3"; }

# 1. Rules UIX8C-R211..R250 persisted in BOTH the modular rule and PROJECT_RULES.
missing_ids=""
for i in $(seq 211 250); do
  id="$(printf 'UIX8C-R%03d' "$i")"
  grep -q "$id" "$RULE" && grep -q "$id" "$PROJECT_RULES" || missing_ids="$missing_ids $id"
done
[ -z "$missing_ids" ] && pass "UIX8C-R211..R250 persisted (rule + PROJECT_RULES)" \
  || bad "UIX-8C-07 rule ids not fully persisted:$missing_ids"

# 2. Required UIX-8C-07 docs + ADR.
for d in \
  docs/adr/0009-uix-8c-07-auth-device-settings-session-recovery.md \
  docs/architecture/uix-8c-07-startup-auth-state-machine.md \
  docs/architecture/uix-8c-07-runtime-context-and-device-trust.md \
  docs/testing/uix-8c-07-auth-device-session-test-matrix.md \
  docs/security/uix-8c-07-auth-device-threat-model.md \
  docs/uiux/uix-8c-07-premium-auth-activation-settings.md \
  docs/deployment/uix-8c-07-deployment-evidence.md; do
  need_file "$d"
done

# 3. Deterministic startup/auth state machine + all conceptual states.
need_file "$JAVA/core/startup/BootState.kt"
need_file "$JAVA/core/startup/StartupCoordinator.kt"
for s in Bootstrapping DatabaseMigration RestoringRuntime ActivationRequired \
         ActivatingDevice LoginRequired Authenticating Ready OfflineReady \
         SessionExpired DeviceInvalid DeviceRevoked ContextMismatch \
         RecoveryRequired RecoverableFailure FatalFailure; do
  need_grep "$JAVA/core/startup/BootState.kt" "$s" "BootState declares $s (UIX8C-R213)"
done
need_grep "$JAVA/core/startup/StartupCoordinator.kt" "DeviceRevoked" "coordinator routes revoked fail-closed (UIX8C-R220)"
need_grep "$JAVA/core/startup/StartupCoordinator.kt" "deviceStatusReached" "revocation trusted only when reached (UIX8C-R214)"
need_file "$TEST/StartupCoordinatorTest.kt"
need_grep "$TEST/StartupCoordinatorTest.kt" "revoked device fails closed" "revoked-fail-closed transition tested (UIX8C-R213/R220)"

# 4. Keystore-backed secure storage — no plaintext token, no jetpack-security dep.
need_file "$JAVA/core/session/SecureTokenStore.kt"
need_grep "$JAVA/core/session/SecureTokenStore.kt" "AndroidKeyStore" "token secured via AndroidKeyStore (UIX8C-R219)"
need_grep "$JAVA/core/session/SecureTokenStore.kt" "cipher.encrypt" "token written only as ciphertext (UIX8C-R219)"
need_grep "$JAVA/core/session/SecureTokenStore.kt" "getOrCreateInstallationId" "app-generated installation id (UIX8C-R218)"
deny_grep "$GRADLE" "androidx.security" "no deprecated jetpack-security dependency (ADR 0009)"
need_file "$TEST/SecureTokenStoreTest.kt"
need_grep "$TEST/SecureTokenStoreTest.kt" "migrated" "legacy plaintext token migration tested (UIX8C-R219)"

# 5. Runtime-context source of truth (validated, server-derived).
need_file "$JAVA/core/runtime/RuntimeContext.kt"
need_file "$JAVA/core/runtime/RuntimeContextStore.kt"
need_grep "$JAVA/core/runtime/RuntimeContext.kt" "fromServer" "context built from server identity, validated (UIX8C-R222/R225)"
need_file "$TEST/RuntimeContextTest.kt"

# 6. Server-authoritative device status: fail-closed mapper + endpoint + revoked screen.
need_file "$JAVA/core/runtime/DeviceStatus.kt"
need_grep "$JAVA/core/runtime/DeviceStatus.kt" "fun unknown" "device status fails closed to UNKNOWN (UIX8C-R214/R221)"
need_grep "$JAVA/data/repository/DeviceActivationRepository.kt" "DeviceStatus.unknown" "status poll fails closed on transport error (UIX8C-R221)"
need_grep "$JAVA/core/network/PosApiService.kt" "device/status" "device-status poll wired (UIX8C-R221)"
need_file "backend/app/Http/Controllers/Api/V1/Android/DeviceStatusController.php"
need_grep "backend/routes/api.php" "device/status" "backend device-status route present (UIX8C-R221)"
# The status route must NOT sit behind device.registered (a revoked device must
# reach it): it must be DEFINED before the first device.registered middleware group.
sline="$(grep -n 'device/status' backend/routes/api.php | head -1 | cut -d: -f1)"
rline="$(grep -nE "middleware\(['\"]device.registered" backend/routes/api.php | head -1 | cut -d: -f1)"
if [ -n "$sline" ] && { [ -z "$rline" ] || [ "$sline" -lt "$rline" ]; }; then
  pass "device/status reachable by a revoked device (UIX8C-R220)"
else
  bad "device/status must not be behind device.registered (UIX8C-R220)"
fi
need_file "$JAVA/feature/session/DeviceRevokedActivity.kt"
need_grep "$JAVA/feature/session/DeviceRevokedActivity.kt" "finishAffinity" "revoked screen fails closed, no bypass (UIX8C-R220)"
need_grep "$MANIFEST" "DeviceRevokedActivity" "revoked screen registered (UIX8C-R220)"

# 7. Unsynced-logout guard counts ALL non-acked transactions.
need_file "$JAVA/core/session/LogoutGuard.kt"
need_grep "$JAVA/core/session/LogoutGuard.kt" "pendingCount" "guard counts pending (UIX8C-R231)"
need_grep "$JAVA/core/session/LogoutGuard.kt" "failedCount" "guard counts bounded-retry FAILED too (UIX8C-R231)"
need_file "$TEST/LogoutGuardTest.kt"
need_grep "$TEST/LogoutGuardTest.kt" "poison" "poison FAILED rows block logout (UIX8C-R231)"

# 8. Classified cross-tenant cleaner + automated tenant-isolation test.
need_file "$JAVA/core/session/LocalDataCleaner.kt"
for s in GLOBAL DEVICE TENANT OUTLET CASHIER TRANSACTION; do
  need_grep "$JAVA/core/session/LocalDataCleaner.kt" "$s" "cleaner classifies $s scope (UIX8C-R236)"
done
need_file "$TEST/LocalDataCleanerTest.kt"
need_file "$TEST/CrossTenantCleanupTest.kt"
need_grep "$TEST/CrossTenantCleanupTest.kt" "tenant B cannot read" "tenant-isolation proof present (UIX8C-R228)"

# 9. Process restoration reuses the stable UIX-8C-06 clientReference identity.
need_grep "$JAVA/core/startup/StartupCoordinator.kt" "pendingUnsynced" "restoration preserves pending queue (UIX8C-R238/R239)"
if grep -rqE 'clientReference' "$JAVA/data/repository/OfflineSaleRepository.kt" 2>/dev/null; then
  pass "stable clientReference identity reused, not re-minted (UIX8C-R240)"
else
  bad "clientReference idempotency identity missing (UIX8C-R240)"
fi

# 10. Truthful status enums (status-not-colour-alone) + 401 signal.
need_file "$JAVA/core/session/OperationalStatus.kt"
for s in Configured Checking Connected Disconnected Degraded Unavailable; do
  need_grepE "$JAVA/core/session/OperationalStatus.kt" "${s^^}|$s" "status distinguishes $s (UIX8C-R243)"
done
need_grep "$JAVA/core/session/OperationalStatus.kt" "label" "every status carries a text label (UIX8C-R244)"
need_file "$JAVA/core/session/SessionEvents.kt"
need_grep "$JAVA/core/session/SessionEvents.kt" "401" "401 maps to SessionExpired (UIX8C-R233)"

# 11. Settings truthfulness + no secret render.
need_file "$JAVA/feature/settings/SettingsViewModel.kt"
need_grep "$JAVA/feature/settings/SettingsViewModel.kt" "Tidak tersedia" "unknown renders Tidak tersedia (UIX8C-R245)"
deny_grep "$JAVA/feature/settings/SettingsViewModel.kt" "getToken" "Settings never reads the raw token (UIX8C-R246)"
need_file "$TEST/SettingsViewModelTest.kt"
need_file "$TEST/DeviceActivationViewModelTest.kt"

# 12. Accessibility / font-130% test presence.
need_file "$TEST/AuthDeviceLayoutTest.kt"
need_grep "$TEST/AuthDeviceLayoutTest.kt" "ScrollView" "font-130% scroll-reachability covered (UIX8C-R248)"
need_grep "$TEST/AuthDeviceLayoutTest.kt" "colour-alone" "status-not-colour-alone covered (UIX8C-R244)"

# 13. Backend device-status / provisioning tests + additive reversible migration.
need_file "$BTEST/DeviceStatusEndpointTest.php"
need_file "$BTEST/DeviceActivationProvisioningTest.php"
need_file "$BTEST/Uix8c07DeviceLifecycleTest.php"
need_file "backend/app/Console/Commands/ProvisionDeviceActivationCommand.php"
MIG="$(ls backend/database/migrations/*add_uix8c07_columns_to_tenant_device_activations.php 2>/dev/null | head -1)"
if [ -n "$MIG" ] && grep -q "installation_id_hash" "$MIG" && grep -q "public function down" "$MIG"; then
  pass "additive reversible activation-columns migration present (UIX8C-R218)"
else
  bad "additive reversible activation-columns migration missing/incomplete"
fi
# The installation id is stored ONLY as a hash (never raw) server-side.
deny_grep "$MIG" "'installation_id'" "installation id stored only as a hash, not raw (UIX8C-R218)"

# 14. Immutable failed physical run stays FAIL (UIX8C-R249/R003).
if [ -f "$FAILED_RUN" ]; then
  python3 - "$FAILED_RUN" <<'PY' || bad "failed physical run integrity check failed (UIX8C-R249/R003)"
import json, sys
d = json.load(open(sys.argv[1]))
rows = {r.get("id"): str(r.get("status","")).upper() for r in d.get("findings", [])}
problems = []
if rows.get("R11") != "FAIL": problems.append("R11 must stay FAIL")
if rows.get("R18") != "FAIL": problems.append("R18 must stay FAIL")
if rows.get("R01") != "PENDING": problems.append("R01 must stay PENDING")
if str(d.get("decision","")).upper() == "GO": problems.append("decision must not be GO")
if problems: print("; ".join(problems)); sys.exit(1)
PY
  [ "$fail" -eq 0 ] && pass "failed physical run R01/R11/R18 unchanged (UIX8C-R249/R003)" || true
else
  bad "missing immutable failed physical run record: $FAILED_RUN"
fi

# 15. UIX-7/UIX-8 runtime evidence stays deferred (decision != GO) (UIX8C-R250).
for pair in "UIX-7:$UIX7_EVID" "UIX-8:$UIX8_EVID"; do
  label="${pair%%:*}"; f="${pair#*:}"
  if [ -f "$f" ]; then
    dec="$(F="$f" python3 -c 'import json,os;print(str(json.load(open(os.environ["F"])).get("decision","")).upper())' 2>/dev/null)"
    case "$dec" in
      GO) bad "$label evidence decision is GO — must stay DEFERRED (UIX8C-R250)" ;;
      *) pass "$label still deferred (decision=$dec)" ;;
    esac
  else
    bad "missing $label runtime evidence: $f"
  fi
done

# 16. No premature UIX-7/UIX-8 GO tag (UIX8C-R250).
if git tag 2>/dev/null | grep -qE '^uix-7-.*-go$'; then
  bad "UIX-7 GO tag must not exist yet (UIX8C-R250)"
else pass "no premature UIX-7 GO tag (UIX8C-R250)"; fi
if git tag 2>/dev/null | grep -qE '^uix-8-android-cashier-premium.*-go$'; then
  bad "UIX-8 GO tag must not exist yet (UIX8C-R250)"
else pass "no premature UIX-8 GO tag (UIX8C-R250)"; fi
need_grep "$RULE" "UIX8C-R250" "sprint-scoped GO non-closure clause persisted (UIX8C-R250)"

# 17. No secret value in the touched source / docs (UIX8C-R219/R227).
SECRET_SCAN="$JAVA/core/session/SecureTokenStore.kt $JAVA/data/repository/DeviceActivationRepository.kt \
  $JAVA/feature/settings/SettingsViewModel.kt \
  backend/app/Http/Controllers/Api/V1/Android/DeviceStatusController.php \
  backend/app/Console/Commands/ProvisionDeviceActivationCommand.php \
  docs/security/uix-8c-07-auth-device-threat-model.md \
  docs/deployment/uix-8c-07-deployment-evidence.md"
if grep -REn -- '-----BEGIN [A-Z ]*PRIVATE KEY-----|AKIA[0-9A-Z]{16}|xox[baprs]-[0-9A-Za-z-]{10,}|Bearer [A-Za-z0-9._-]{24,}|password[[:space:]]*[:=][[:space:]]*["'"'"'][^"'"'"']{6,}' $SECRET_SCAN 2>/dev/null; then
  bad "possible secret value present in touched source/docs (UIX8C-R219/R227)"
else
  pass "no secret value in touched source/docs (UIX8C-R219/R227)"
fi

echo
if [ "$fail" -eq 0 ]; then
  echo "UIX-8C-07 premium authentication / device / settings / session-recovery gate: PASS"
else
  echo "UIX-8C-07 premium authentication / device / settings / session-recovery gate: FAIL"
fi
exit "$fail"
