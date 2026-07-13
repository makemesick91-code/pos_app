#!/usr/bin/env bash
#
# UIX-7 pilot connectivity & network-security gate (UIX7-R045..UIX7-R051).
#
# Static, build-free checks (this environment has no Android SDK) that make the
# physical-device pilot connectivity fix machine-verifiable:
#   - a dedicated `pilot` build type exists, is installable (debug-signed),
#     debuggable, and targets the governed HTTPS backend;
#   - emulator `debug` keeps the 10.0.2.2 host alias, but `pilot`/`release` never do;
#   - the src/main network policy denies cleartext with the system trust store only
#     (no trust-all, no hostname override), and the local cleartext exceptions live
#     ONLY in the debug source set;
#   - no HTTP logging runs for the debuggable pilot variant.
# CI additionally compiles the variant-aware ApiBaseUrlVariantTest, which asserts
# the same contract against each variant's generated BuildConfig.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }

GRADLE=android/app/build.gradle.kts
MAIN_NSC=android/app/src/main/res/xml/network_security_config.xml
DEBUG_NSC=android/app/src/debug/res/xml/network_security_config.xml
APICLIENT=android/app/src/main/java/com/aishtech/poslite/core/network/ApiClient.kt
VARIANT_TEST=android/app/src/test/java/com/aishtech/poslite/ApiBaseUrlVariantTest.kt
APP_SRC=android/app/src/main

echo "== UIX-7 pilot connectivity & network security gate =="

# 1. Dedicated pilot build type (UIX7-R045/R049).
grep -Eq 'create\("pilot"\)' "$GRADLE" && pass "pilot build type defined" || bad "no pilot build type in $GRADLE"
grep -q 'initWith(getByName("debug"))' "$GRADLE" && pass "pilot initWith(debug)" || bad "pilot does not initWith(debug)"
grep -q 'signingConfig = signingConfigs.getByName("debug")' "$GRADLE" && pass "pilot debug-signed (installable)" || bad "pilot not debug-signed"
grep -Eq 'isDebuggable = true' "$GRADLE" && pass "pilot debuggable for controlled verification" || bad "pilot not debuggable"

# 2. Endpoint contract: debug=emulator http, pilot+release=HTTPS pilot (UIX7-R046).
grep -q 'buildConfigField("String", "API_BASE_URL", "\\"http://10.0.2.2:8000/\\"")' "$GRADLE" \
  && pass "emulator debug URL present (http://10.0.2.2:8000/)" || bad "emulator debug URL missing/changed"
https_count=$(grep -c 'buildConfigField("String", "API_BASE_URL", "\\"https://aishpos.online/\\"")' "$GRADLE" || true)
if [ "$https_count" -ge 2 ]; then
  pass "pilot AND release set the HTTPS pilot URL ($https_count occurrences)"
else
  bad "expected >=2 HTTPS pilot URL overrides (pilot + release), found $https_count"
fi

# 3. No forbidden host reaches a non-debug endpoint override. The only http:// URL
#    allowed anywhere in the Gradle config is the emulator 10.0.2.2 alias.
if grep -nE 'API_BASE_URL.*http://' "$GRADLE" | grep -vq '10\.0\.2\.2'; then
  bad "a non-emulator cleartext API_BASE_URL override exists"
else
  pass "the only cleartext API_BASE_URL is the emulator alias"
fi
for forbidden in 'aishpos\.online:8080' '145\.79\.13\.224' 'http://aishpos\.online' 'http://localhost' 'API_BASE_URL.*127\.0\.0\.1'; do
  if grep -Eq "$forbidden" "$GRADLE"; then bad "forbidden endpoint token in gradle: $forbidden"; fi
done
[ "$fail" -eq 0 ] && pass "no forbidden endpoint tokens in gradle config" || true

# 4. src/main network policy denies cleartext, trusts system store only, no local hosts (UIX7-R047/R048).
[ -f "$MAIN_NSC" ] || bad "missing $MAIN_NSC"
grep -q 'cleartextTrafficPermitted="false"' "$MAIN_NSC" && pass "main config denies cleartext" || bad "main config does not deny cleartext"
grep -q '<certificates src="system"' "$MAIN_NSC" && pass "main config trusts system store" || bad "main config missing system trust store"
if grep -Eq '10\.0\.2\.2|localhost|127\.0\.0\.1|cleartextTrafficPermitted="true"' "$MAIN_NSC"; then
  bad "src/main network config leaks a local cleartext exception into pilot/release"
else
  pass "src/main has no local cleartext exception (pilot/release are TLS-only)"
fi

# 5. Local cleartext exceptions are debug-source-set ONLY (UIX7-R048).
[ -f "$DEBUG_NSC" ] || bad "missing debug-only $DEBUG_NSC"
grep -q '10.0.2.2' "$DEBUG_NSC" && pass "debug-only config carries the 10.0.2.2 exception" || bad "debug config missing 10.0.2.2 exception"

# 6. No trust-all TLS or hostname-validation override anywhere in main sources (UIX7-R047).
if grep -REnq 'TrustManager|SSLContext\.getInstance|HostnameVerifier|hostnameVerifier|trustAll|X509TrustManager|checkServerTrusted' "$APP_SRC/java"; then
  bad "a trust-all / hostname-override TLS construct exists in main sources"
else
  pass "no trust-all / hostname-override TLS construct in main sources"
fi

# 7. HTTP logging never attaches to the debuggable pilot variant (UIX7-R047/R026).
grep -q 'BuildConfig.BUILD_TYPE == "debug"' "$APICLIENT" \
  && pass "HTTP logging gated to the debug build type only (not pilot)" \
  || bad "HTTP logging not gated to debug build type (pilot could log)"
grep -q 'redactHeader("Authorization")' "$APICLIENT" && pass "Authorization header redacted in logging" || bad "Authorization header not redacted"

# 8. Variant-aware endpoint test present (compiled per variant by CI).
[ -f "$VARIANT_TEST" ] && pass "ApiBaseUrlVariantTest present" || bad "missing ApiBaseUrlVariantTest"

# 9. Rules UIX7-R045..R051 documented in the canonical rule files (UIX7-R045..R051).
for i in 45 46 47 48 49 50 51; do
  grep -q "UIX7-R0$i" .claude/rules/55-android-cashier-experience.md || bad "rule 55 missing UIX7-R0$i"
  grep -q "UIX7-R0$i" docs/PROJECT_RULES.md || bad "PROJECT_RULES missing UIX7-R0$i"
  grep -q "UIX7-R0$i" docs/foundation/uix-7-android-cashier-experience-remediation.md || bad "foundation doc missing UIX7-R0$i"
done
[ "$fail" -eq 0 ] && pass "UIX7-R045..R051 documented in rule 55 + PROJECT_RULES + foundation doc" || true

[ "$fail" -eq 0 ] || { echo "UIX-7 PILOT CONNECTIVITY GATE: FAIL"; exit 1; }
echo "UIX-7 PILOT CONNECTIVITY GATE: PASS"
