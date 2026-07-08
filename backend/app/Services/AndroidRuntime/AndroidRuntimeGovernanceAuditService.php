<?php

namespace App\Services\AndroidRuntime;

/**
 * Sprint 34 — audits that the Android runtime governance is wired the way the ADR
 * rules require (config flags + rule registry + hard guardrails + doc contract).
 * Pure configuration/structure inspection: never mutates, charges, deploys, or
 * leaks secrets. Produces PASS/WARN/FAIL signals consumed by go-no-go (ADR-R030).
 */
class AndroidRuntimeGovernanceAuditService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    /**
     * @return array<int, array{key: string, status: string, message: string}>
     */
    public function evaluate(): array
    {
        return [
            $this->rulesPresentSignal(),
            $this->guardrailsSignal(),
            $this->tokenHashedSignal(),
            $this->runtimeBehaviorSignal(),
            $this->offlineBoundedSignal(),
            $this->syncIdempotencySignal(),
            $this->redactionSignal(),
            $this->docsSignal(),
        ];
    }

    private function rulesPresentSignal(): array
    {
        $rules = (array) config('android_runtime_governance.rules', []);
        $missing = [];

        for ($i = 1; $i <= 30; $i++) {
            $code = sprintf('ADR-R%03d', $i);
            if (! array_key_exists($code, $rules)) {
                $missing[] = $code;
            }
        }

        return $this->signal(
            'rules_registry',
            $missing === [] ? self::STATUS_PASS : self::STATUS_FAIL,
            $missing === []
                ? 'All ADR-R001..ADR-R030 rules are declared in config.'
                : 'Missing android runtime rules: '.implode(', ', $missing).'.',
        );
    }

    private function guardrailsSignal(): array
    {
        $flags = [
            'raw_activation_token_returned_after_creation_allowed',
            'raw_activation_token_stored_allowed',
            'android_marks_invoice_paid_allowed',
            'android_unlocks_entitlement_locally_allowed',
            'sync_bypasses_pos_domain_service_allowed',
            'revoked_device_can_sync_allowed',
            'duplicate_client_uuid_double_mutation_allowed',
            'runtime_bypasses_entitlement_service_allowed',
            'manual_suspension_overridable_by_billing_allowed',
            'raw_credential_in_output_allowed',
        ];

        $violations = [];
        foreach ($flags as $flag) {
            if (config('android_runtime_governance.'.$flag) !== false) {
                $violations[] = $flag;
            }
        }

        return $this->signal(
            'hard_guardrails',
            $violations === [] ? self::STATUS_PASS : self::STATUS_FAIL,
            $violations === []
                ? 'All hard Android runtime guardrails are locked false.'
                : 'Guardrail(s) not locked false: '.implode(', ', $violations).'.',
        );
    }

    private function tokenHashedSignal(): array
    {
        $algo = (string) config('android_runtime_governance.activation_token.hash_algo', '');
        $returned = config('android_runtime_governance.activation_token.return_raw_token_after_creation');
        $stored = config('android_runtime_governance.activation_token.store_raw_token');
        $ok = in_array($algo, ['sha256', 'sha512'], true) && $returned === false && $stored === false;

        return $this->signal(
            'activation_token_hashed',
            $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok
                ? 'Activation token is hashed ('.$algo.'), non-reversible, never stored/returned raw.'
                : 'Activation token hashing/non-reversibility policy is not enforced.',
        );
    }

    private function runtimeBehaviorSignal(): array
    {
        $suspended = (string) config('android_runtime_governance.runtime_behavior.suspended', '');
        $unpaid = (string) config('android_runtime_governance.runtime_behavior.unpaid_past_grace', '');
        $trial = (string) config('android_runtime_governance.runtime_behavior.trial_expired', '');
        $allowed = ['block', 'read_only'];
        $ok = $suspended === 'block' && in_array($unpaid, $allowed, true) && in_array($trial, $allowed, true);

        return $this->signal(
            'runtime_fail_closed',
            $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok
                ? 'Suspended blocks; unpaid-past-grace/trial-expired are block or read_only (fail closed).'
                : 'Runtime behavior is not fail-closed for suspended/unpaid/trial states.',
        );
    }

    private function offlineBoundedSignal(): array
    {
        $items = (int) config('android_runtime_governance.offline.queue_max_items', 0);
        $age = (int) config('android_runtime_governance.offline.queue_max_age_hours', 0);
        $ok = $items > 0 && $age > 0;

        return $this->signal(
            'offline_queue_bounded',
            $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok ? "Offline queue bounded ({$items} items, {$age}h)." : 'Offline queue is not bounded by size/age.',
        );
    }

    private function syncIdempotencySignal(): array
    {
        $batch = config('android_runtime_governance.sync.batch_idempotency_required');
        $itemId = config('android_runtime_governance.sync.require_item_client_id');
        $clientUuid = config('android_runtime_governance.offline.require_client_uuid');
        $ok = $batch === true && $itemId === true && $clientUuid === true;

        return $this->signal(
            'sync_idempotency_required',
            $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok
                ? 'Batch idempotency + per-item client UUID are required.'
                : 'Sync idempotency / client-UUID requirement is not enforced.',
        );
    }

    private function redactionSignal(): array
    {
        $ok = (bool) config('android_runtime_governance.redaction.required', false)
            && (bool) config('android_runtime_governance.redaction.redact_metadata', false);

        return $this->signal(
            'redaction_required',
            $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok ? 'Redaction is required for all runtime output/metadata.' : 'Redaction requirement is not enforced.',
        );
    }

    private function docsSignal(): array
    {
        $missing = [];
        foreach ((array) config('android_runtime_governance.required_docs', []) as $doc) {
            if (! is_file(base_path('../'.$doc)) && ! is_file(base_path($doc)) && ! is_file(dirname(base_path()).'/'.$doc)) {
                $missing[] = $doc;
            }
        }

        return $this->signal(
            'docs_contract',
            $missing === [] ? self::STATUS_PASS : self::STATUS_WARN,
            $missing === [] ? 'Required Android runtime docs are present.' : 'Missing docs: '.implode(', ', $missing).'.',
        );
    }

    /**
     * @return array{key: string, status: string, message: string}
     */
    private function signal(string $key, string $status, string $message): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message];
    }
}
