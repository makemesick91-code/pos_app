# UIX-8C-07 — Deployment & Runtime Evidence

Sprint: **UIX-8C-07 — Premium Authentication, Device Activation, Settings & Session Recovery**
Captured: 2026-07-15. All runtime evidence below is bound to the exact merged candidate.

> **Honesty note.** Emulator evidence in this document is **labelled emulator** and is
> authoritative only for hardware-independent scenarios (visual rendering / layout /
> font-scale resilience) per UIX7-R071..R080. Operator-observed 130% + TalkBack human
> PASS remains a separate checkpoint captured on a real device after code freeze and is
> **never fabricated** (UIX8C-R249). This sprint creates **no** UIX-7/UIX-8 GO tag:
> **UIX-7 stays `NO-GO — GO DEFERRED`; UIX-8 stays `IMPLEMENTATION COMPLETE — GO
> DEFERRED`.** The immutable failed physical run `run-97fbb64-2af94aa` (R11/R18 FAIL,
> R01 PENDING) is unchanged.

## 1. Baseline
| Item | Value |
|------|-------|
| `origin/main` (pre-sprint) | `1e37a936a4d1feaa902b9af0860f1033e3f5986b` |
| UIX-8C-06 evidence anchor | `1e37a93` (match) |
| VPS aish HEAD (before) | `1e37a936…` |
| DMS `asia-dental-lab-v2` HEAD (before) | `8b0bb6af0a11624d34887e5b70e3a0c7627e34b4` |
| Graphify map | android graph refreshed → 2069 nodes / 3413 edges / 132 communities |

## 2. Implementation
| Item | Value |
|------|-------|
| Branch | `feature/uix-8c-07-premium-auth-device-settings-session-recovery` |
| Final implementation candidate SHA | `d0a5fccf32ebc88690a7dd751b1f0f3ae55d65cf` |
| Implementation PR | **#79** (`makemesick91-code/pos_app`) |
| Authoritative CI run | `29387267103` — **SUCCESS** (single "Authoritative summary" gate PASS; Android all-variant, Backend suite, governance, Foundation gates, Security all PASS; classifier→full CI; evidence lane correctly skipped) |
| Merge commit SHA | `f41eaab92312e95094993e29332d9d50db098414` (candidate is an ancestor) |

## 3. Tests (on the candidate)
| Suite | Result |
|-------|--------|
| Android unit (`testDebugUnitTest`) | **333 / 0** (48 new UIX-8C-07 methods) |
| Backend (`php artisan test`) | **1535 / 0**, 46042 assertions (10 new UIX-8C-07) |
| Sprint-34 device regression | **41 / 0** (no regression) |
| UIX-8C-07 gate `scripts/uix8c_auth_device_session_gate.sh` | **PASS** (99 checks) |
| Gate self-tests | **15 / 15** fail-closed cases |
| Prior UIX-8C gates (01..06) + CICD-2 + foundation | **PASS** |
| Android lint (`lintDebug` + `lintVitalRelease`) | **PASS** |
| Android variants built | debug / pilot / release — **all SUCCESSFUL** |

## 4. Android artifact
| Item | Value |
|------|-------|
| APK (pilot, installable) | `android/app/build/outputs/apk/pilot/app-pilot.apk` |
| Package | `com.aishtech.poslite` |
| Version | `0.1.0` (versionCode 1), variant `pilot` (→ `https://aishpos.online/`, TLS-only) |
| APK SHA-256 | `007413b047b70a010303c8d6fc0e37d43a1e20c50c517e60c8fb6fb02513aaba` |
| Signing | debug/pilot certificate (installable pilot) |
| Source commit | `f41eaab` (post-merge; identical app source to candidate `d0a5fcc`) |

## 5. Deployment (VPS pilot, shared with DaengtisiaMS)
| Step | Result |
|------|--------|
| DB backup (pre) | operator-held `pg_dump -Fc` of the pilot DB captured pre-deploy (1.1M); path retained in the operator log, not published |
| Rollback reference | previous release commit `1e37a93` + pre-deploy backup above |
| Deploy mechanism | Git (`git fetch origin main` → ff-only) — official traceable path |
| VPS aish HEAD (after) | `f41eaab92312e95094993e29332d9d50db098414` ✅ = merge commit |
| Migration | `2026_09_23_999100_add_uix8c07_columns_to_tenant_device_activations` — **DONE** (22.57ms) |
| Cache | `config:cache` + `route:cache` rebuilt as `www-data` |
| Runtime file ownership | **0** root-owned files under `storage/framework` / `bootstrap/cache` |
| Queue worker | `aish-pos-queue-worker` restarted |
| Services (after) | `aish-pos-queue-worker`, `php8.5-fpm`, `php8.3-fpm`, `nginx` — all **active** |

## 6. Runtime smoke (deployed build)
| Check | Result |
|-------|--------|
| Route registered | `GET\|HEAD api/v1/android/device/status` present in `route:list` ✅ |
| `GET https://aishpos.online/` | **200** |
| `GET /health/live` | **200** |
| `GET /health/ready` | **200** |
| `GET /api/v1/android/device/status` (no auth) | **500** — pre-existing android-group behavior (`device/activate` and `runtime/policy`, unchanged routes, also 500 unauthenticated); the endpoint does not leak data, and the Android fail-closed `DeviceStatusMapper` treats any non-2xx as **not-active**. Not a UIX-8C-07 regression. |
| `GET /api/v1/auth/login` | **405** (POST-only, as expected) |
| Authenticated `device/status` behavior | proven by `DeviceStatusEndpointTest` (not_activated / active / revoked+reason / tenant-isolation) — 10/10 green in authoritative CI on this exact commit; deployed code byte-identical. A live-token production impersonation smoke was deliberately **not** run (production-account safety). |

## 7. DaengtisiaMS non-regression
| Item | Before | After |
|------|--------|-------|
| DMS `asia-dental-lab-v2` HEAD | `8b0bb6a` | `8b0bb6a` ✅ unchanged |
| DMS worktree | clean | **0 changes** |
| `php8.3-fpm` | active | active |

## 8. Emulator runtime evidence (labelled emulator)
- Environment: Android Emulator, AVD `uix8c07_evidence`, system image `android-34;google_apis_playstore;x86_64`, KVM-accelerated. Debug-signed `app-debug` (same UI source as candidate). Captured via an in-process `ActivityScenario` decor-view harness (one-shot, not committed).
- Screenshots (`docs/evidence/uix-8c-07/screenshots/`), each at **100%** and **130%** system font:
  - `activation_emulator_{100,130}.png` — premium device-activation entry.
  - `session_expired_emulator_{100,130}.png` — session-expired recovery ("Sesi Anda telah berakhir", pending preserved).
  - `device_revoked_emulator_{100,130}.png` — fail-closed revoked screen (reason + device/outlet + "Tutup aplikasi").
  - `settings_emulator_{100,130}.png` — Settings: truthful "Tidak tersedia" for unknowns, **shortened** installation id (`95dec851`, no secret), scroll-reachable sections at 130%.
- Font scale restored to 1.0 after capture. No token / secret / PII appears in any screenshot.
- **Deferred (never fabricated):** operator-observed TalkBack + human 130% PASS, and the backend-driven active/revoked/session-expired transitions on a physical device — part of the post-freeze physical campaign.

## 9. Evidence closure
| Item | Value |
|------|-------|
| Evidence branch | `evidence/uix-8c-07-premium-auth-device-settings-session-recovery` |
| Evidence PR | recorded in the closure commit / final report |
| Final evidence merge SHA | recorded post-merge |
| local == origin == VPS HEAD | verified at GO (see §10) |

## 10. GO decision
Recorded in the final closure report and the annotated sprint tag
`uix-8c-07-premium-authentication-device-activation-settings-session-recovery-go`
(created only after the closure gate PASS + exact-match; the tag confirms sprint
implementation closure only and never asserts UIX-7/UIX-8 runtime closure).
