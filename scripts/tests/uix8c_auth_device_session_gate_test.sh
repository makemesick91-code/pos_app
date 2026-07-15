#!/usr/bin/env bash
#
# Regression tests for scripts/uix8c_auth_device_session_gate.sh (UIX-8C-07).
# Proves the auth/device/settings/session gate is fail-closed: it passes on the
# real tree and rejects (1) a dropped rule id, (2) the device-status route moved
# behind device.registered, (3) a SecureTokenStore without AndroidKeyStore, (4) a
# jetpack-security dependency, (5) a logout guard that ignores FAILED rows, (6) a
# missing tenant-isolation test, (7) a device-status mapper that is not
# fail-closed, (8) a missing BootState state, (9) Settings reading the raw token,
# (10) missing accessibility/font tests, (11) a non-reversible migration, (12)
# mutated historical failed-run evidence, (13) a premature UIX-8 GO tag, and (14) a
# secret value in touched evidence.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"; cd "$ROOT"
GATE=scripts/uix8c_auth_device_session_gate.sh
fails=0
ok(){ printf '  [ok]   %s\n' "$1"; }
no(){ printf '  [BAD]  %s\n' "$1"; fails=1; }

echo "== uix8c auth / device / settings / session gate regression =="

JAVA="android/app/src/main/java/com/aishtech/poslite"
TEST="android/app/src/test/java/com/aishtech/poslite"
RULE=".claude/rules/61-android-cashier-full-premium-delivery-foundation.md"
FRUN="docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json"
THREAT="docs/security/uix-8c-07-auth-device-threat-model.md"
SECURE="$JAVA/core/session/SecureTokenStore.kt"
BOOT="$JAVA/core/startup/BootState.kt"
GUARD="$JAVA/core/session/LogoutGuard.kt"
DSTATUS="$JAVA/core/runtime/DeviceStatus.kt"
SETTINGS_VM="$JAVA/feature/settings/SettingsViewModel.kt"
GRADLE="android/app/build.gradle.kts"
ROUTES="backend/routes/api.php"

# 0. Real repo -> PASS.
bash "$GATE" >/dev/null 2>&1; RC=$?
[ "$RC" -eq 0 ] && ok "gate passes on the real tree" || no "gate should pass on the real tree (rc=$RC)"

mksandbox(){
  local d; d="$(mktemp -d)"
  mkdir -p "$d/android/app/src" "$d/.claude" "$d/scripts/tests" \
    "$d/backend/app/Http/Controllers/Api/V1/Android" \
    "$d/backend/app/Console/Commands" "$d/backend/database/migrations" \
    "$d/backend/tests/Feature" "$d/backend/routes"
  cp -r android/app/src/main "$d/android/app/src/"
  cp -r android/app/src/test "$d/android/app/src/"
  cp "$GRADLE" "$d/$GRADLE"
  cp -r .claude/rules "$d/.claude/"
  cp -r docs "$d/"
  cp "$ROUTES" "$d/$ROUTES"
  cp backend/app/Http/Controllers/Api/V1/Android/DeviceStatusController.php "$d/backend/app/Http/Controllers/Api/V1/Android/"
  cp backend/app/Console/Commands/ProvisionDeviceActivationCommand.php "$d/backend/app/Console/Commands/"
  cp backend/database/migrations/*add_uix8c07_columns_to_tenant_device_activations.php "$d/backend/database/migrations/"
  cp backend/tests/Feature/DeviceStatusEndpointTest.php backend/tests/Feature/DeviceActivationProvisioningTest.php \
     backend/tests/Feature/Uix8c07DeviceLifecycleTest.php "$d/backend/tests/Feature/"
  cp "$GATE" "$d/scripts/"
  echo "$d"
}
runsb(){ ( cd "$1" && bash "$GATE" >/dev/null 2>&1 ); }

# 1. Clean sandbox -> PASS.
SB="$(mksandbox)"; runsb "$SB"; RC=$?
[ "$RC" -eq 0 ] && ok "gate passes on a clean sandbox mirror" || no "clean sandbox should pass (rc=$RC)"
rm -rf "$SB"

# 2. Drop a UIX-8C-07 rule id -> FAIL.
SB="$(mksandbox)"; sed -i 's/UIX8C-R221/UIX8C-RXXX/g' "$SB/$RULE"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a dropped rule id is rejected (UIX8C-R211..R250)" || no "dropped rule id must be rejected"
rm -rf "$SB"

# 3. Move device/status behind device.registered -> FAIL.
SB="$(mksandbox)"
sed -i "s#Route::get('/device/status', \[AndroidDeviceStatusController::class, 'show'\]);##" "$SB/$ROUTES"
sed -i "s#Route::middleware('device.registered')->group(function () {#Route::middleware('device.registered')->group(function () {\n    Route::get('/device/status', [AndroidDeviceStatusController::class, 'show']);#" "$SB/$ROUTES"
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "device/status behind device.registered is rejected (UIX8C-R220)" || no "status behind device.registered must be rejected"
rm -rf "$SB"

# 4. SecureTokenStore without AndroidKeyStore -> FAIL.
SB="$(mksandbox)"; sed -i 's/AndroidKeyStore/PlainStore/g' "$SB/$SECURE"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a token store without AndroidKeyStore is rejected (UIX8C-R219)" || no "non-Keystore token store must be rejected"
rm -rf "$SB"

# 5. A jetpack-security dependency -> FAIL.
SB="$(mksandbox)"; printf '\n    implementation("androidx.security:security-crypto:1.1.0-alpha06")\n' >> "$SB/$GRADLE"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a jetpack-security dependency is rejected (ADR 0009)" || no "jetpack-security dependency must be rejected"
rm -rf "$SB"

# 6. Logout guard ignores FAILED rows -> FAIL.
SB="$(mksandbox)"; sed -i 's/failedCount/ignoredCount/g' "$SB/$GUARD"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a guard ignoring FAILED rows is rejected (UIX8C-R231)" || no "guard ignoring FAILED must be rejected"
rm -rf "$SB"

# 7. Missing tenant-isolation test -> FAIL.
SB="$(mksandbox)"; rm -f "$SB/$TEST/CrossTenantCleanupTest.kt"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a missing tenant-isolation test is rejected (UIX8C-R228)" || no "missing isolation test must be rejected"
rm -rf "$SB"

# 8. Device-status mapper not fail-closed (no unknown()) -> FAIL.
SB="$(mksandbox)"; sed -i 's/fun unknown/fun assumeActive/g' "$SB/$DSTATUS"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a non-fail-closed device-status mapper is rejected (UIX8C-R221)" || no "non-fail-closed mapper must be rejected"
rm -rf "$SB"

# 9. Missing BootState state -> FAIL.
SB="$(mksandbox)"; sed -i 's/DeviceRevoked/DeviceGone/g' "$SB/$BOOT"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a missing BootState state is rejected (UIX8C-R213)" || no "missing BootState must be rejected"
rm -rf "$SB"

# 10. Settings reads the raw token -> FAIL.
SB="$(mksandbox)"; printf '\n// leak: session.getToken()\n' >> "$SB/$SETTINGS_VM"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "Settings reading the raw token is rejected (UIX8C-R246)" || no "Settings token read must be rejected"
rm -rf "$SB"

# 11. Missing accessibility/font test -> FAIL.
SB="$(mksandbox)"; rm -f "$SB/$TEST/AuthDeviceLayoutTest.kt"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a missing accessibility/font test is rejected (UIX8C-R248)" || no "missing a11y/font test must be rejected"
rm -rf "$SB"

# 12. Non-reversible migration (no down()) -> FAIL.
SB="$(mksandbox)"; sed -i 's/public function down/public function noDown/g' "$SB"/backend/database/migrations/*add_uix8c07*; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a non-reversible migration is rejected (UIX8C-R218)" || no "non-reversible migration must be rejected"
rm -rf "$SB"

# 13. Flip historical failed R11 FAIL -> PASS -> FAIL.
SB="$(mksandbox)"
python3 - "$SB/$FRUN" <<'PY'
import json,sys
p=sys.argv[1]; d=json.load(open(p))
for f in d.get("findings",[]):
    if f.get("id")=="R11": f["status"]="PASS"
json.dump(d,open(p,"w"))
PY
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "flipping historical R11 to PASS is rejected (UIX8C-R249/R003)" || no "flipped R11 must be rejected"
rm -rf "$SB"

# 14. Premature UIX-8 GO tag -> FAIL.
SB="$(mksandbox)"
( cd "$SB" && git init -q && git -c user.email=t@t.co -c user.name=t commit --allow-empty -qm x \
  && git tag uix-8-android-cashier-premium-visual-transaction-experience-go ) >/dev/null 2>&1
runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a premature UIX-8 GO tag is rejected (UIX8C-R250)" || no "premature UIX-8 GO tag must be rejected"
rm -rf "$SB"

# 15. Secret value in touched evidence -> FAIL.
SB="$(mksandbox)"; printf '\nAKIAABCDEFGHIJKLMNOP\n' >> "$SB/$THREAT"; runsb "$SB"; RC=$?
[ "$RC" -ne 0 ] && ok "a secret value in touched evidence is rejected (UIX8C-R219/R227)" || no "secret value must be rejected"
rm -rf "$SB"

[ "$fails" -eq 0 ] && { echo "UIX-8C-07 AUTH / DEVICE / SESSION GATE TEST: PASS"; exit 0; } \
                   || { echo "UIX-8C-07 AUTH / DEVICE / SESSION GATE TEST: FAIL"; exit 1; }
