<?php

namespace App\Services\AndroidRuntime;

use App\Models\RegisteredDevice;
use App\Models\Tenant;
use App\Models\TenantDeviceActivation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sprint 34 — the ONLY governed path for Android device/register activation
 * (ADR-R002).
 *
 * Token model (ADR-R003): the raw activation token is generated once by prepare()
 * and handed to the merchant/device out-of-band. Only its sha256 hash is stored;
 * the raw token is never persisted, logged or returned again. activate() verifies
 * the presented token by hash and never receives it back into any output.
 *
 * Idempotency (ADR-R004): activation is idempotent per tenant + device fingerprint
 * — a retry from the same device returns the same activation and never creates a
 * second RegisteredDevice. Entitlement (ADR-R005): activation runs the Sprint 32
 * device limit + billing/lifecycle gate through AndroidRuntimeAccessService, so a
 * suspended/unpaid/over-limit/unknown-plan tenant fails closed (ADR-R006/R007).
 */
class DeviceActivationService
{
    public function __construct(
        private readonly AndroidRuntimeAccessService $access,
        private readonly AndroidRuntimeAuditService $audit,
        private readonly AndroidSyncRedactor $redactor,
    ) {}

    /**
     * Prepare a pending activation and return the one-time raw token. The caller
     * MUST NOT log or persist the raw token beyond handing it to the device.
     *
     * @return array{activation: TenantDeviceActivation, token: string}
     */
    public function prepare(
        Tenant $tenant,
        ?int $storeId = null,
        ?int $registerId = null,
        ?User $actor = null,
        ?int $ttlMinutes = null,
        ?int $provisioningRunId = null,
    ): array {
        $rawToken = Str::random(40);
        $ttl = $ttlMinutes ?? (int) config('android_runtime_governance.activation_token_ttl_minutes', 1440);

        $activation = TenantDeviceActivation::query()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $storeId,
            'register_id' => $registerId,
            'provisioning_run_id' => $provisioningRunId,
            'activation_status' => TenantDeviceActivation::STATUS_PENDING,
            'activation_token_hash' => $this->hashToken($rawToken),
            'expires_at' => Carbon::now()->addMinutes($ttl),
            'metadata_json' => $this->redactor->redact(['prepared_by' => $actor?->id]),
        ]);

        return ['activation' => $activation, 'token' => $rawToken];
    }

    /**
     * Activate a device with a raw token + device fingerprint. Idempotent per
     * (tenant, fingerprint). Never stores/returns the raw token.
     */
    public function activate(
        Tenant $tenant,
        string $rawToken,
        string $fingerprint,
        ?string $deviceUuid = null,
        ?string $label = null,
        ?User $actor = null,
        ?string $appVersion = null,
        ?string $installationId = null,
    ): TenantDeviceActivation {
        $rawToken = trim($rawToken);
        $fingerprint = trim($fingerprint);

        $min = (int) config('android_runtime_governance.activation_token.min_token_length', 8);
        $max = (int) config('android_runtime_governance.activation_token.max_token_length', 128);

        if ($fingerprint === '') {
            throw new AndroidRuntimeException('Device fingerprint is required.', 'INVALID_FINGERPRINT', 422);
        }

        if (mb_strlen($rawToken) < $min || mb_strlen($rawToken) > $max) {
            throw new AndroidRuntimeException('Activation token is invalid.', 'INVALID_ACTIVATION_TOKEN', 422);
        }

        $tokenHash = $this->hashToken($rawToken);
        $fingerprintHash = $this->hashFingerprint($fingerprint);

        // Idempotency (ADR-R004): a usable activation for this fingerprint returns
        // as-is without creating a second device.
        $existing = TenantDeviceActivation::query()
            ->forTenant($tenant->id)
            ->where('device_fingerprint_hash', $fingerprintHash)
            ->where('activation_status', TenantDeviceActivation::STATUS_ACTIVATED)
            ->first();

        if ($existing instanceof TenantDeviceActivation && $existing->isUsable()) {
            $existing->forceFill(['last_seen_at' => Carbon::now()])->save();

            return $existing;
        }

        $activation = TenantDeviceActivation::query()
            ->forTenant($tenant->id)
            ->where('activation_token_hash', $tokenHash)
            ->first();

        if (! $activation instanceof TenantDeviceActivation) {
            if (! (bool) config('android_runtime_governance.activation_token.allow_auto_prepare', true)) {
                throw new AndroidRuntimeException('Unknown or expired activation token.', 'INVALID_ACTIVATION_TOKEN', 403);
            }

            $ttl = (int) config('android_runtime_governance.activation_token_ttl_minutes', 1440);
            $activation = TenantDeviceActivation::query()->create([
                'tenant_id' => $tenant->id,
                'activation_status' => TenantDeviceActivation::STATUS_PENDING,
                'activation_token_hash' => $tokenHash,
                'expires_at' => Carbon::now()->addMinutes($ttl),
            ]);
        }

        if ($activation->isRevoked()) {
            throw new AndroidRuntimeException('Activation has been revoked.', 'ACTIVATION_REVOKED', 403);
        }

        if ($activation->isExpired()) {
            $activation->forceFill(['activation_status' => TenantDeviceActivation::STATUS_EXPIRED])->save();
            throw new AndroidRuntimeException('Activation token has expired.', 'ACTIVATION_EXPIRED', 403);
        }

        // Register/device mismatch (ADR-R027): a pending activation already bound to
        // a different fingerprint may not be claimed by another device.
        if ($activation->device_fingerprint_hash !== null && $activation->device_fingerprint_hash !== $fingerprintHash) {
            $this->fail($activation, 'REGISTER_MISMATCH', 'Device fingerprint does not match this activation.');
            throw new AndroidRuntimeException('Device does not match this activation.', 'REGISTER_MISMATCH', 403);
        }

        $maxAttempts = (int) config('android_runtime_governance.max_activation_attempts', 5);
        if ($activation->attempt_count >= $maxAttempts) {
            $this->fail($activation, 'MAX_ATTEMPTS', 'Maximum activation attempts exceeded.');
            throw new AndroidRuntimeException('Maximum activation attempts exceeded.', 'MAX_ATTEMPTS', 429);
        }
        $activation->increment('attempt_count');

        // Entitlement + billing/lifecycle gate (ADR-R005/R006/R007). Fails closed.
        $decision = $this->access->authorizeActivation($tenant, $actor);
        if ($decision->denied()) {
            $this->fail($activation, $decision->reasonCode, $decision->message);
            throw new AndroidRuntimeException($decision->message, $decision->reasonCode, $decision->httpStatus);
        }

        return DB::transaction(function () use ($tenant, $activation, $fingerprintHash, $deviceUuid, $label, $actor, $appVersion, $installationId) {
            $uuid = $deviceUuid !== null && trim($deviceUuid) !== ''
                ? trim($deviceUuid)
                : 'act-'.substr($fingerprintHash, 0, 40);

            $device = RegisteredDevice::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'device_uuid' => $uuid],
                [
                    'user_id' => $actor?->id,
                    'store_id' => $activation->store_id,
                    'device_name' => $label,
                    'platform' => RegisteredDevice::PLATFORM_ANDROID,
                    'last_seen_at' => Carbon::now(),
                    'registered_at' => Carbon::now(),
                    'revoked_at' => null,
                    'status' => RegisteredDevice::STATUS_ACTIVE,
                ],
            );

            $activation->forceFill([
                'activation_status' => TenantDeviceActivation::STATUS_ACTIVATED,
                'device_fingerprint_hash' => $fingerprintHash,
                'device_id' => $device->id,
                'device_label' => $label,
                // UIX-8C-07 — capture support/triage metadata. The installation id
                // is an app-generated id (never a hardware id) stored ONLY as a
                // hash; the raw value is never persisted (UIX8C-R218/R219).
                'app_version' => $appVersion !== null && trim($appVersion) !== '' ? trim($appVersion) : $activation->app_version,
                'installation_id_hash' => $installationId !== null && trim($installationId) !== ''
                    ? $this->hashFingerprint(trim($installationId))
                    : $activation->installation_id_hash,
                'activated_by_user_id' => $actor?->id,
                'activated_at' => Carbon::now(),
                'last_seen_at' => Carbon::now(),
                'failure_reason' => null,
                // UIX-8C-08 (DEF-002) — expires_at belongs to the single-use
                // activation CODE, not to the activated DEVICE. The code is
                // consumed here, so its TTL MUST be cleared; otherwise
                // isExpired() would flip an ACTIVE device to 'expired' once the
                // code TTL elapsed (default 24h) and every cashier device would
                // fail closed. Device validity is governed by revocation only.
                'expires_at' => null,
            ])->save();

            if ($actor !== null) {
                $this->audit->recordAdminAction(
                    $actor,
                    AndroidRuntimeAuditService::ACTION_DEVICE_ACTIVATED,
                    $activation,
                    ['device_id' => $device->id],
                );
            }

            return $activation->refresh();
        });
    }

    /**
     * UIX-8C-08 — code-authenticated, device-first activation. A genuinely fresh
     * device (no cashier session yet) presents ONLY the single-use activation code.
     * The tenant is resolved from the code record itself — never from client input
     * or an authenticated session — so the device-first activation gate works
     * without a bearer token (UIX8C-R217/R063). The code MUST already exist (issued
     * by device:provision-activation / prepare()); this path never auto-prepares,
     * so an unknown code can never self-provision a device (fail closed). All
     * canonical checks (expiry, revoked, fingerprint binding, attempt cap,
     * entitlement/lifecycle gate, single-use, idempotency) still run inside
     * activate(); the actor is null (no user yet) which is already supported.
     */
    public function activateWithCode(
        string $rawToken,
        string $fingerprint,
        ?string $deviceUuid = null,
        ?string $label = null,
        ?string $appVersion = null,
        ?string $installationId = null,
    ): TenantDeviceActivation {
        $tenant = $this->resolveTenantForCode($rawToken);

        return $this->activate(
            tenant: $tenant,
            rawToken: $rawToken,
            fingerprint: $fingerprint,
            deviceUuid: $deviceUuid,
            label: $label,
            actor: null,
            appVersion: $appVersion,
            installationId: $installationId,
        );
    }

    /**
     * Resolve the tenant a single-use activation code belongs to, by hash ONLY.
     * The raw code is never trusted as tenant input; an unknown or (defensively)
     * ambiguous code fails closed with no auto-prepare, so the public activation
     * endpoint can never bind a device to a caller-chosen tenant.
     */
    public function resolveTenantForCode(string $rawToken): Tenant
    {
        $rawToken = trim($rawToken);

        $min = (int) config('android_runtime_governance.activation_token.min_token_length', 8);
        $max = (int) config('android_runtime_governance.activation_token.max_token_length', 128);
        if (mb_strlen($rawToken) < $min || mb_strlen($rawToken) > $max) {
            throw new AndroidRuntimeException('Activation code is invalid.', 'INVALID_ACTIVATION_TOKEN', 422);
        }

        $tokenHash = $this->hashToken($rawToken);
        $matches = TenantDeviceActivation::query()
            ->where('activation_token_hash', $tokenHash)
            ->get();

        // Exactly one issued code must match. Zero = unknown/expired-and-purged
        // code; more than one = ambiguous — both fail closed (never self-provision).
        if ($matches->count() !== 1) {
            throw new AndroidRuntimeException('Unknown or expired activation code.', 'INVALID_ACTIVATION_TOKEN', 403);
        }

        $tenant = Tenant::query()->find($matches->first()->tenant_id);
        if (! $tenant instanceof Tenant) {
            throw new AndroidRuntimeException('Unknown or expired activation code.', 'INVALID_ACTIVATION_TOKEN', 403);
        }

        return $tenant;
    }

    /**
     * Bridge a pre-existing Sprint 10 RegisteredDevice to a Sprint 34 activation
     * record so the runtime gate/sync ledger has a canonical activation to reference.
     * Finds the device's usable activation or lazily creates an ACTIVATED one. A
     * REVOKED/BLOCKED device yields a revoked activation so sync stays blocked
     * (ADR-R026). Never creates a second RegisteredDevice.
     */
    public function resolveForDevice(RegisteredDevice $device): TenantDeviceActivation
    {
        $existing = TenantDeviceActivation::query()
            ->forTenant($device->tenant_id)
            ->where('device_id', $device->id)
            ->orderByDesc('id')
            ->first();

        if ($existing instanceof TenantDeviceActivation) {
            if (! $device->isActive() && ! $existing->isRevoked()) {
                $existing->forceFill([
                    'activation_status' => TenantDeviceActivation::STATUS_REVOKED,
                    'revoked_at' => Carbon::now(),
                ])->save();
            }

            return $existing;
        }

        return TenantDeviceActivation::query()->create([
            'tenant_id' => $device->tenant_id,
            'store_id' => $device->store_id,
            'device_id' => $device->id,
            'activation_status' => $device->isActive()
                ? TenantDeviceActivation::STATUS_ACTIVATED
                : TenantDeviceActivation::STATUS_REVOKED,
            'device_fingerprint_hash' => $this->hashFingerprint((string) $device->device_uuid),
            'device_label' => $device->device_name,
            'activated_by_user_id' => $device->user_id,
            'activated_at' => $device->isActive() ? Carbon::now() : null,
            'revoked_at' => $device->isActive() ? null : Carbon::now(),
            'last_seen_at' => Carbon::now(),
        ]);
    }

    /**
     * Update the last-seen timestamp for an activated device (heartbeat).
     */
    public function heartbeat(TenantDeviceActivation $activation): TenantDeviceActivation
    {
        if ($activation->isUsable()) {
            $activation->forceFill(['last_seen_at' => Carbon::now()])->save();
        }

        return $activation;
    }

    public function hashToken(string $raw): string
    {
        $algo = (string) config('android_runtime_governance.activation_token.hash_algo', 'sha256');

        return hash($algo, $raw);
    }

    public function hashFingerprint(string $fingerprint): string
    {
        $algo = (string) config('android_runtime_governance.activation_token.fingerprint_hash_algo', 'sha256');

        return hash($algo, $fingerprint);
    }

    private function fail(TenantDeviceActivation $activation, string $reasonCode, string $message): void
    {
        $activation->forceFill([
            'activation_status' => TenantDeviceActivation::STATUS_FAILED,
            'failure_reason' => $reasonCode.': '.$message,
        ])->save();
    }
}
